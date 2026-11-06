<?php

namespace App\Services\Compliance;

use App\Models\Compliance\AlertDismissal;
use App\Models\Compliance\CompanyDocument;
use App\Models\Compliance\CompanyPolicy;
use App\Models\Compliance\EmploymentContract;
use App\Models\Employee;
use App\Models\User;

/**
 * Live document-status figures for the Compliance Overview. Every number is counted from the records;
 * nothing here claims the company is or is not legally compliant.
 */
class Overview
{
    public static function stats(): array
    {
        $employees = Employee::workforce()->count();
        $withExecuted = EmploymentContract::where('status', 'executed')->distinct('employee_id')->count('employee_id');
        $withUploaded = CompanyDocument::whereNotNull('employee_id')->where('status', 'active')
            ->whereHas('category', fn ($q) => $q->where('name', 'Employment contracts'))->distinct('employee_id')->pluck('employee_id');
        $covered = EmploymentContract::where('status', 'executed')->pluck('employee_id')->merge($withUploaded)->unique();
        $without = Employee::workforce()->whereNotIn('id', $covered)->count();

        $policies = CompanyPolicy::whereNotNull('active_version')->where('status', '!=', 'archived')->get();
        $unacked = 0;
        $unackedEmployees = collect();
        foreach ($policies as $p) {
            $o = Policies::outstanding($p);
            $unacked += $o->count();
            $unackedEmployees = $unackedEmployees->merge($o->pluck('id'));
        }

        return [
            'employees'          => $employees,
            'with_contract'      => $covered->count(),
            'executed'           => $withExecuted,
            'without_contract'   => $without,
            'awaiting_employee'  => EmploymentContract::where('status', 'sent')->count(),
            'awaiting_approval'  => EmploymentContract::where('status', 'in_approval')->count(),
            'returned'           => EmploymentContract::whereIn('status', ['declined', 'changes', 'rejected'])->count(),
            'fixed_term_ending'  => EmploymentContract::where('status', 'executed')->whereNotNull('end_date')->whereBetween('end_date', [now()->startOfDay(), now()->addDays(60)])->count(),
            'active_policies'    => $policies->count(),
            'policies_awaiting'  => CompanyPolicy::where('status', 'in_approval')->count(),
            'unacknowledged'     => $unackedEmployees->unique()->count(),
            'ack_gaps'           => $unacked,
            'policies_review'    => CompanyPolicy::whereNotNull('active_version')->where('status', '!=', 'archived')->whereNotNull('review_date')->where('review_date', '<=', now()->addDays(30))->count(),
            'documents'          => CompanyDocument::where('status', 'active')->count(),
            'docs_expiring'      => CompanyDocument::where('status', 'active')->whereNotNull('expiry_date')->whereBetween('expiry_date', [now()->startOfDay(), now()->addDays(30)])->count(),
            'docs_expired'       => CompanyDocument::where('status', 'active')->whereNotNull('expiry_date')->where('expiry_date', '<', now()->startOfDay())->count(),
            'docs_review'        => CompanyDocument::where('status', 'active')->whereNotNull('review_date')->where('review_date', '<=', now()->addDays(30))->count(),
        ];
    }

    /** Attention items. Dismissing hides one until its number changes; it never changes the underlying status. */
    public static function alerts(array $s, User $user): array
    {
        $defs = [
            ['without_contract', 'warn', fn ($n) => "{$n} " . str('employee')->plural($n) . " " . ($n === 1 ? 'has' : 'have') . " no completed employment contract recorded in ZamPayroll.", route('compliance.reports.index', ['report' => 'without_contract'])],
            ['awaiting_employee', 'info', fn ($n) => "{$n} " . str('contract')->plural($n) . " " . ($n === 1 ? 'is' : 'are') . " waiting for the employee to sign.", route('compliance.contracts.index', ['status' => 'sent'])],
            ['awaiting_approval', 'info', fn ($n) => "{$n} " . str('contract')->plural($n) . " " . ($n === 1 ? 'is' : 'are') . " waiting for management approval.", route('compliance.contracts.index', ['status' => 'in_approval'])],
            ['returned', 'warn', fn ($n) => "{$n} " . str('contract')->plural($n) . " came back declined or with changes requested.", route('compliance.contracts.index', ['status' => 'returned'])],
            ['fixed_term_ending', 'warn', fn ($n) => "{$n} fixed-term " . str('contract')->plural($n) . ($n === 1 ? ' ends' : ' end') . " in the next 60 days.", route('compliance.reports.index', ['report' => 'fixed_term_ending'])],
            ['unacknowledged', 'info', fn ($n) => "{$n} " . str('employee')->plural($n) . " still need to acknowledge a company policy.", route('compliance.acknowledgements')],
            ['policies_review', 'warn', fn ($n) => "{$n} " . str('policy')->plural($n) . " " . ($n === 1 ? 'is' : 'are') . " due for review.", route('compliance.policies.index', ['filter' => 'review'])],
            ['docs_expiring', 'warn', fn ($n) => "{$n} " . str('document')->plural($n) . ($n === 1 ? ' expires' : ' expire') . " in the next 30 days.", route('compliance.documents.index', ['expiry' => 'soon'])],
            ['docs_expired', 'danger', fn ($n) => "{$n} " . str('document')->plural($n) . " " . ($n === 1 ? 'has' : 'have') . " expired.", route('compliance.documents.index', ['expiry' => 'expired'])],
        ];
        $dismissed = AlertDismissal::where('user_id', $user->id)->pluck('count_at_dismissal', 'alert_key');
        $out = [];
        foreach ($defs as [$key, $tone, $text, $url]) {
            $n = (int) ($s[$key] ?? 0);
            if ($n > 0 && (int) ($dismissed[$key] ?? -1) !== $n) {
                $out[] = ['key' => $key, 'tone' => $tone, 'text' => $text($n), 'url' => $url, 'count' => $n];
            }
        }

        return $out;
    }
}
