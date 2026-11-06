<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Compliance\CompanyDocument;
use App\Models\Compliance\CompanyPolicy;
use App\Models\Compliance\ComplianceSetting;
use App\Models\Compliance\EmploymentContract;
use App\Services\Compliance\ComplianceMailer;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daily: email HR about documents that expire or are due for review, policies due for review and fixed-term
 * contracts that end soon, at each company's reminder intervals (default 90, 30, 7 and 0 days). Each reminder is sent once.
 */
class ComplianceReminders extends Command
{
    protected $signature = 'compliance:reminders {--company= : Only this company id}';

    protected $description = 'Send Compliance Centre expiry and review reminders';

    public function handle(): int
    {
        if (! Schema::hasTable('compliance_reminder_logs')) {
            return self::SUCCESS;
        }
        $companies = Company::query()->when($this->option('company'), fn ($q, $id) => $q->whereKey($id))->get();
        $total = 0;
        foreach ($companies as $company) {
            try {
                $run = fn () => Tenancy::runAs((int) $company->id, fn () => $this->forCompany((int) $company->id));
                // Dedicated workspaces keep their records in their own database.
                $total += $company->isDedicated() && $company->database_name && $company->status === 'active'
                    ? Tenancy::runInDedicated($company, $run) : $run();
            } catch (\Throwable $e) {
                report($e);
                $this->warn("{$company->name}: " . $e->getMessage());
            }
        }
        $this->info("Sent {$total} reminder(s).");

        return self::SUCCESS;
    }

    private function forCompany(int $cid): int
    {
        $days = ComplianceSetting::current()->reminderDays();
        $items = [];
        foreach (CompanyDocument::where('status', 'active')->whereNotNull('expiry_date')->get() as $d) {
            $items[] = ['document', $d->id, 'expiry', $d->expiry_date, "Document expiring: {$d->title}", 'expires', route('compliance.documents.show', $d)];
        }
        foreach (CompanyDocument::where('status', 'active')->whereNotNull('review_date')->get() as $d) {
            $items[] = ['document', $d->id, 'review', $d->review_date, "Document review due: {$d->title}", 'is due for review', route('compliance.documents.show', $d)];
        }
        foreach (CompanyPolicy::whereNotNull('active_version')->where('status', '!=', 'archived')->whereNotNull('review_date')->get() as $p) {
            $items[] = ['policy', $p->id, 'policy_review', $p->review_date, "Policy review due: {$p->title}", 'is due for review', route('compliance.policies.show', $p)];
        }
        foreach (EmploymentContract::with('employee')->where('status', 'executed')->whereNotNull('end_date')->get() as $c) {
            $items[] = ['contract', $c->id, 'contract_end', $c->end_date, "Contract ending: {$c->employee?->full_name} ({$c->reference})", 'ends', route('compliance.contracts.show', $c)];
        }

        $sent = 0;
        $recipients = ComplianceMailer::staffWith('compliance.documents')->merge(ComplianceMailer::staffWith('compliance.contracts'))->unique('id');
        foreach ($items as [$type, $id, $kind, $date, $title, $verb, $url]) {
            $left = (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);
            // Send the reminder for the tightest interval we have reached (e.g. 30 when 29 days are left), once.
            $due = collect($days)->filter(fn ($d) => $left <= $d)->min();
            if ($due === null || $left < 0 && $due !== 0) {
                continue;
            }
            $inserted = DB::table('compliance_reminder_logs')->insertOrIgnore([
                'company_id' => $cid, 'subject_type' => $type, 'subject_id' => $id, 'kind' => $kind, 'target_date' => $date->toDateString(),
                'days_before' => $due, 'sent_at' => now(),
            ]);
            if (! $inserted) {
                continue;
            }
            $when = $left > 0 ? "in {$left} " . str('day')->plural($left) . ' (' . $date->format('j M Y') . ')' : ($left === 0 ? 'today' : 'on ' . $date->format('j M Y'));
            ComplianceMailer::send('reminder.' . $kind, $recipients, [
                'subject' => $title, 'eyebrow' => 'Compliance reminder', 'heading' => $title,
                'intro' => "This {$verb} {$when}.", 'url' => $url, 'label' => 'Open in ZamPayroll',
            ], $type, $id);
            $sent++;
        }

        return $sent;
    }
}
