<?php

namespace App\Services\Billing;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Prices and billing rules, editable in the ZamPayroll admin console.
 * Falls back to config/billing.php.
 */
class PlatformSettings
{
    public const EDITABLE = [
        'free_limit'                => ['Free plan: up to this many active employees', 'int'],
        'trial_days'                => ['Free trial once they go over the free limit (days)', 'int'],
        'prepaid_limit'             => ['Legacy Starter plan: up to this many employees', 'int'],
        'starter_monthly'           => ['Starter: price per month (K)', 'money'],
        'starter_annual'            => ['Starter: price per year (K)', 'money'],
        'business_per_employee'     => ['Business: per employee per month (K)', 'money'],
        'business_minimum'          => ['Business: minimum monthly invoice (K)', 'money'],
        'private_workspace_monthly' => ['Private sub-domain add-on per month (K)', 'money'],
        'vat_rate'                  => ['VAT rate (%)', 'money'],
        'invoice_due_days'          => ['Post-paid invoices due after (days)', 'int'],
        'grace_days'                => ['Grace period before limiting access (days)', 'int'],
        'renewal_notice_days'       => ['Send renewal invoice this many days early', 'int'],
        'partner_commission_rate'   => ['Partners: commission on referred invoices (%)', 'money'],
        'partner_commission_months' => ['Partners: pay commission for this many months (0 = always)', 'int'],
        'partner_auto_approve'      => ['Partners: approve applications automatically (1 = yes, 0 = review first)', 'int'],
    ];

    public static function get(string $key)
    {
        $all = Cache::remember('platform_settings', 300, function () {
            try {
                return Schema::connection(\App\Support\Tenancy::landlordConnection())->hasTable('platform_settings')
                    ? PlatformSetting::query()->pluck('value', 'key')->all() : [];
            } catch (\Throwable $e) {
                return [];
            }
        });

        $value = array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '' ? $all[$key] : config('billing.' . $key);
        $type = self::EDITABLE[$key][1] ?? null;

        return match ($type) {
            'int' => (int) $value,
            'money' => (float) $value,
            default => $value,
        };
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            if (isset(self::EDITABLE[$key])) {
                PlatformSetting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
            }
        }
        Cache::forget('platform_settings');
    }

    public static function all(): array
    {
        return collect(self::EDITABLE)->mapWithKeys(fn ($v, $k) => [$k => self::get($k)])->all();
    }
}
