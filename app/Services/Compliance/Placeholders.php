<?php

namespace App\Services\Compliance;

use App\Models\Company;
use App\Models\Compliance\ComplianceSetting;
use App\Models\Employee;
use Carbon\Carbon;

/**
 * Fills {{placeholders}} from ZamPayroll's own records so HR never retypes what the system already knows.
 * Anything missing is reported so HR can complete it before the document goes out.
 */
class Placeholders
{
    /** Values from the company record (and compliance defaults). */
    public static function company(Company $company, ?ComplianceSetting $settings = null): array
    {
        $settings ??= ComplianceSetting::current();

        return [
            'company_name' => $company->name,
            'company_address' => collect([$company->address, $company->city, $company->province])->filter()->implode(', '),
            'company_phone' => $company->phone,
            'company_email' => $company->email,
            'company_website' => $company->website,
            'company_tpin' => $company->tpin,
            'company_registration_number' => $company->pacra_registration_number,
            'company_napsa_number' => $company->napsa_employer_number,
            'today' => now()->format('j F Y'),
            'effective_date' => now()->format('j F Y'),
            'signatory_name' => $settings->signatory_name,
            'signatory_title' => $settings->signatory_title,
            'policy_owner' => $settings->signatory_title ?: 'the Human Resources office',
            'working_hours' => $settings->working_hours,
            'notice_period' => $settings->notice_period,
            'allowances' => $settings->allowances,
            'probation_months' => (string) ($settings->probation_months ?: 3),
            'pay_frequency' => match ($company->pay_frequency) { 'weekly' => 'weekly', 'fortnightly' => 'every two weeks', default => 'monthly' },
            'pay_day' => $company->pay_day ? (string) $company->pay_day : null,
        ];
    }

    /** Values for one employee and contract type. */
    public static function employee(Employee $e, Company $company, string $contractType, ?ComplianceSetting $settings = null): array
    {
        $settings ??= ComplianceSetting::current();
        $start = $e->contract_start ?? $e->probation_start;
        $end = $e->contract_end;
        $months = (int) ($settings->probation_months ?: 3);
        $perMonth = $e->leave_days_entitled ? max(2, round($e->leave_days_entitled / 12, 1)) : 2;

        $duration = null;
        if ($start && $end && $end->gt($start)) {
            $m = (int) round($start->floatDiffInMonths($end));
            $duration = $m >= 1 ? $m . ' ' . ($m === 1 ? 'month' : 'months') : $start->diffInDays($end) . ' days';
        }

        return [
            'employee_full_name' => trim(collect([$e->first_name, $e->middle_name, $e->last_name])->filter()->implode(' ')),
            'employee_number' => $e->employee_id,
            'employee_nrc' => $e->nrc_no,
            'employee_passport' => $e->passport_number,
            'employee_nationality' => $e->nationality,
            'employee_date_of_birth' => $e->date_of_birth?->format('j F Y'),
            'employee_address' => null, // ZamPayroll has no residential address field: HR completes it
            'employee_phone' => $e->primary_phone,
            'employee_email' => $e->personal_email ?: $e->company_email,
            'employee_position' => $e->position,
            'employee_department' => $e->department,
            'work_location' => $e->branch ?: ($company->city ?: null),
            'reporting_manager' => $e->supervisor,
            'employment_type' => TemplateLibrary::CONTRACT_TYPES[$contractType] ?? null,
            'start_date' => $start?->format('j F Y'),
            'contract_end_date' => $end?->format('j F Y'),
            'contract_duration' => $duration,
            'probation_end_date' => $start ? Carbon::parse($start)->addMonths($months)->subDay()->format('j F Y') : null,
            'basic_salary' => $e->salary ? 'K' . number_format((float) $e->salary, 2) . ($contractType === 'casual' ? '' : ' a month') : null,
            'hourly_rate' => $e->daily_rate_salary ? 'K' . number_format((float) $e->daily_rate_salary, 2) . ' a day' : null,
            'leave_days_per_month' => rtrim(rtrim(number_format($perMonth, 1), '0'), '.'),
            'job_responsibilities' => null,
        ];
    }

    /** Keys used in a body, in order of first use. */
    public static function used(string $html): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/', $html, $m);

        return array_values(array_unique($m[1]));
    }

    /** Keys used in the body that have no value yet. */
    public static function missing(string $html, array $values): array
    {
        return array_values(array_filter(self::used($html), fn ($k) => ! isset($values[$k]) || trim((string) $values[$k]) === ''));
    }

    /**
     * Replace placeholders with escaped values. $highlight marks missing ones for screen previews;
     * otherwise a missing value becomes a blank line to fill by hand.
     */
    public static function fill(string $html, array $values, bool $highlight = false): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/', function ($m) use ($values, $highlight) {
            $v = $values[$m[1]] ?? null;
            if ($v === null || trim((string) $v) === '') {
                $label = TemplateLibrary::PLACEHOLDERS[$m[1]] ?? str_replace('_', ' ', $m[1]);

                return $highlight ? '<span class="ph-missing">[' . e($label) . ']</span>' : '____________________';
            }

            // Multi-line values (like duties) keep their line breaks.
            return nl2br(e((string) $v), false);
        }, $html);
    }

    public static function label(string $key): string
    {
        return TemplateLibrary::PLACEHOLDERS[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }
}
