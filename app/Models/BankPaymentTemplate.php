<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankPaymentTemplate extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = [
        'company_id',
        'bank_id',
        'name',
        'source',
        'delimiter',
        'include_header',
        'amount_decimal_places',
        'columns',
        'is_default',
        'created_by',
    ];

    protected $casts = [
        'columns' => 'array',
        'include_header' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Every field a template column can be mapped to. Shown in the mapping UI
     * whether the company picked a preset or is mapping their own uploaded CSV.
     */
    const FIELDS = [
        'account_number' => 'Beneficiary Bank Account Number',
        'account_name' => 'Beneficiary Full Name',
        'amount' => 'Net Pay Amount',
        'swift_code' => 'Beneficiary Bank SWIFT/BIC Code (auto-detected from employee bank)',
        'sort_code' => 'Beneficiary Bank Sort/Branch Code',
        'bank_name' => 'Beneficiary Bank Name',
        'bank_branch' => 'Beneficiary Bank Branch',
        'reference' => 'Payment Reference (e.g. pay period)',
        'beneficiary_reference' => 'Beneficiary Reference',
        'employee_id' => 'Employee ID / Staff Number',
        'nrc_no' => 'National Registration Card (NRC) Number',
        'email' => 'Employee Email',
        'phone' => 'Employee Phone Number',
        'address' => 'Employee / Branch Address',
        'static' => 'Fixed Text (same value on every row)',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * The universal starting-point template: the shape confirmed against a real
     * bank's bulk upload file (see PayrollReportController's original exporter).
     * Companies pick this when their own bank's exact format isn't in the preset
     * list yet, then adjust headers/prefixes as needed — or replace it entirely
     * by uploading their bank's own template.
     */
    public static function genericColumns(): array
    {
        return [
            ['header' => '# Account Number', 'field' => 'account_number', 'prefix' => '#', 'suffix' => '', 'static_value' => null],
            ['header' => 'Amount', 'field' => 'amount', 'prefix' => '', 'suffix' => '', 'static_value' => null],
            ['header' => 'Beneficiary Name', 'field' => 'account_name', 'prefix' => '', 'suffix' => '', 'static_value' => null],
            ['header' => 'My Reference', 'field' => 'reference', 'prefix' => '', 'suffix' => '', 'static_value' => null],
            ['header' => 'Beneficiary Reference', 'field' => 'beneficiary_reference', 'prefix' => '', 'suffix' => '', 'static_value' => null],
            ['header' => '# Bank Sort Code', 'field' => 'sort_code', 'prefix' => '#', 'suffix' => '', 'static_value' => null],
            ['header' => 'SWIFT Code', 'field' => 'swift_code', 'prefix' => '', 'suffix' => '', 'static_value' => null],
            ['header' => 'Address', 'field' => 'address', 'prefix' => '', 'suffix' => '', 'static_value' => null],
        ];
    }

    /**
     * Build one CSV row (array of strings, in column order) for a payroll/employee
     * against this template's column mapping. $context carries everything a column
     * might need to pull from.
     *
     * $context keys expected: employee, payroll, run, company, matchedBank, reference
     */
    public function buildRow(array $context): array
    {
        $employee = $context['employee'];
        $payroll = $context['payroll'];
        $matchedBank = $context['matchedBank'];

        $values = [
            'account_number' => $employee->bank_account_no ?? '',
            'account_name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
            'amount' => number_format((float) ($payroll->net_pay ?? 0), $this->amount_decimal_places, '.', ''),
            'swift_code' => $matchedBank->swift_code ?? ($employee->bank_swift_code ?? ''),
            'sort_code' => $employee->bank_sort_code ?? ($matchedBank->sort_code ?? ''),
            'bank_name' => $employee->bank_name ?? ($matchedBank->name ?? ''),
            'bank_branch' => $employee->bank_branch ?? ($payroll->branch ?? ''),
            'reference' => $context['reference'] ?? '',
            'beneficiary_reference' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
            'employee_id' => $employee->employee_id ?? $employee->id ?? '',
            'nrc_no' => $employee->nrc_no ?? '',
            'email' => $employee->company_email ?? $employee->personal_email ?? '',
            'phone' => $employee->primary_phone ?? '',
            'address' => $employee->address ?? ($payroll->branch ?? ''),
        ];

        return array_map(function ($col) use ($values) {
            if (($col['field'] ?? null) === 'static') {
                $raw = $col['static_value'] ?? '';
            } else {
                $raw = $values[$col['field']] ?? '';
            }

            return ($col['prefix'] ?? '') . $raw . ($col['suffix'] ?? '');
        }, $this->columns);
    }

    public function headerRow(): array
    {
        return array_map(fn ($col) => $col['header'], $this->columns);
    }
}
