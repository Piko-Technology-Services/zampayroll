<?php

namespace App\Services\Payslips;

use App\Jobs\SendPayslipEmail;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollRun;
use App\Models\User;
use App\Support\Audit;
use App\Support\Tenancy;
use Illuminate\Support\Collection;

/**
 * How payslips reach employees: the self-service portal, their work email,
 * their personal email — any combination, chosen per pay run (with company defaults).
 */
class PayslipDelivery
{
    public const DEFAULTS = ['portal' => true, 'work' => true, 'personal' => false, 'auto_portal' => true];

    public function settings(?Company $company = null): array
    {
        $company ??= Tenancy::company();
        $saved = $company?->payslip_settings;
        $saved = is_string($saved) ? (json_decode($saved, true) ?: []) : (array) $saved;

        return array_map('boolval', array_merge(self::DEFAULTS, array_intersect_key($saved, self::DEFAULTS)));
    }

    public function saveSettings(Company $company, array $values): void
    {
        $merged = array_merge($this->settings($company), array_map('boolval', array_intersect_key($values, self::DEFAULTS)));
        Company::on(Tenancy::landlordConnection())->whereKey($company->id)->update(['payslip_settings' => json_encode($merged)]);
        if (Tenancy::isDedicated()) {
            Company::query()->whereKey($company->id)->update(['payslip_settings' => json_encode($merged)]);
        }
    }

    /** Email addresses for the chosen channels (falls back to whichever address exists). */
    public function recipients(?Employee $employee, bool $work, bool $personal): array
    {
        if (! $employee || (! $work && ! $personal)) {
            return [];
        }

        $list = array_filter([
            $work ? $employee->company_email : null,
            $personal ? $employee->personal_email : null,
        ]);
        if (! $list) { // the chosen address is missing: use the other one rather than send nothing
            $list = array_filter([$employee->company_email, $employee->personal_email]);
        }

        return array_values(array_unique(array_map(fn ($e) => strtolower(trim($e)), array_filter($list, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)))));
    }

    /**
     * Share payslips of a locked run.
     *
     * @param  array{portal:bool, work:bool, personal:bool}  $channels
     * @return array{published:int, emailed:int, no_email:array<int,string>, unpublished:int}
     */
    public function share(PayrollRun $run, Collection $payrolls, array $channels, ?User $by = null): array
    {
        if (! $run->isLocked()) {
            throw new \DomainException('Payslips can be shared once the pay run is approved.');
        }

        $out = ['published' => 0, 'emailed' => 0, 'no_email' => [], 'unpublished' => 0];
        $companyName = Tenancy::company()?->name ?? 'ZamPayroll';

        foreach ($payrolls as $payroll) {
            $payroll->loadMissing('employee');
            if ($channels['portal']) {
                if (! $payroll->published_at) {
                    $payroll->forceFill(['published_at' => now()])->saveQuietly();
                }
                $out['published']++;
            }

            if ($channels['work'] || $channels['personal']) {
                $to = $this->recipients($payroll->employee, $channels['work'], $channels['personal']);
                if (! $to) {
                    $out['no_email'][] = trim(($payroll->employee->first_name ?? '') . ' ' . ($payroll->employee->last_name ?? ''));
                    continue;
                }
                SendPayslipEmail::dispatch($payroll->id, $companyName, null, $to);
                $payroll->forceFill(['emailed_at' => now(), 'emailed_to' => implode(', ', $to)])->saveQuietly();
                $out['emailed']++;
            }
        }

        $how = implode(' + ', array_keys(array_filter(['portal' => $channels['portal'], 'work email' => $channels['work'], 'personal email' => $channels['personal']])));
        Audit::record('payslips.shared', $run, "Payslips for {$run->displayName()} shared by {$how}: {$out['published']} on the portal, {$out['emailed']} emailed", ['payrolls' => $payrolls->pluck('id')->all()]);

        return $out;
    }

    public function unpublish(PayrollRun $run, Collection $payrolls): int
    {
        $n = Payroll::query()->whereIn('id', $payrolls->pluck('id'))->whereNotNull('published_at')->update(['published_at' => null]);
        Audit::record('payslips.unpublished', $run, "{$n} payslip(s) for {$run->displayName()} removed from the portal");

        return $n;
    }

    /** Called when a run is approved and locked. */
    public function onApproved(PayrollRun $run): void
    {
        if ($this->settings()['auto_portal']) {
            Payroll::query()->where('payroll_run_id', $run->id)->whereNull('published_at')->update(['published_at' => now()]);
        }
    }
}
