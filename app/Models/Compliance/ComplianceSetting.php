<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;

class ComplianceSetting extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected $casts = ['reminder_days' => 'array'];

    public const DEFAULT_DISCLAIMER = 'This document was prepared from a ZamPayroll template. Templates are a starting point and have not been reviewed for your circumstances. Have important documents checked by a qualified Zambian employment lawyer. Generating or storing a document in ZamPayroll does not by itself make a company compliant with the law.';

    /** The current company's settings (created with sensible defaults on first use). */
    public static function current(): self
    {
        return static::firstOrCreate(['company_id' => Tenancy::companyId()], [
            'disclaimer' => self::DEFAULT_DISCLAIMER,
            'reminder_days' => [90, 30, 7, 0],
            'retention_years' => 5,
            'working_hours' => '08:00 to 17:00, Monday to Friday, with a one-hour lunch break',
            'notice_period' => 'one month (30 days)',
            'probation_months' => 3,
            'allowances' => 'Any allowances and benefits are as set out in the company\'s payroll rules and any written allowance schedule given to the Employee.',
        ]);
    }

    public function reminderDays(): array
    {
        $days = array_map('intval', $this->reminder_days ?: [90, 30, 7, 0]);
        rsort($days);

        return array_values(array_unique(array_filter($days, fn ($d) => $d >= 0 && $d <= 365)));
    }
}
