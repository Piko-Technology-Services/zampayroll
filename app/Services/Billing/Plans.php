<?php

namespace App\Services\Billing;

/**
 * ZamPayroll's paid plans. The plan follows the number of active employees
 * (board members aren't counted). Prices are editable in Platform → Settings.
 */
class Plans
{
    /** Paid plans in order, with what each one is for. */
    public const TIERS = [
        'tikula'    => ['name' => 'Tikula', 'tagline' => 'Growing Businesses', 'range' => '11–50 active employees', 'max' => 50, 'slug' => 'tikula',
                        'description' => 'Built for ambitious businesses ready to professionalise their HR operations and simplify payroll.'],
        'medium'    => ['name' => 'Medium Enterprise', 'tagline' => 'Popular Choice', 'range' => '51–150 active employees', 'max' => 150, 'slug' => 'medium-enterprise',
                        'description' => 'Designed for established organisations managing larger teams and increasingly complex workforce operations.'],
        'corporate' => ['name' => 'Corporate', 'tagline' => 'Advanced Scale', 'range' => '151–300 active employees', 'max' => 300, 'slug' => 'corporate',
                        'description' => 'For organisations seeking a centralised, intelligent approach to workforce administration and payroll at scale.'],
    ];

    public const ENTERPRISE_MIN = 301;

    /** Tier key for a team size, or null for Enterprise (priced by consultation). */
    public static function forEmployees(int $employees): ?string
    {
        if ($employees >= self::ENTERPRISE_MIN) {
            return null;
        }
        foreach (self::TIERS as $key => $tier) {
            if ($employees <= $tier['max']) {
                return $key;
            }
        }

        return null;
    }

    public static function isPaid(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::TIERS);
    }

    public static function name(?string $key): string
    {
        return self::TIERS[$key]['name'] ?? 'Enterprise';
    }

    public static function price(string $key, string $cycle): float
    {
        return PlatformSettings::get('plan_' . $key . '_' . ($cycle === 'annual' ? 'annual' : 'monthly'));
    }

    /**
     * The secure online payment page for a plan (mobile money prompt or card via DPO Pay), e.g.
     * https://pikozm.com/zampayroll-subscriptions/checkout/medium-enterprise?billing=annual
     */
    public static function onlineCheckoutUrl(string $key, string $cycle, array $extra = []): string
    {
        $slug = self::TIERS[$key]['slug'] ?? $key;

        return rtrim((string) config('billing.online_payment_url'), '/') . '/checkout/' . $slug . '?'
            . http_build_query(['billing' => $cycle === 'annual' ? 'annual' : 'monthly'] + $extra);
    }

    /** Data for the pricing cards: [key => [name, tagline, range, description, monthly, annual]]. */
    public static function cards(): array
    {
        $out = [];
        foreach (self::TIERS as $key => $tier) {
            $out[$key] = $tier + [
                'monthly' => self::price($key, 'monthly'),
                'annual' => self::price($key, 'annual'),
            ];
        }

        return $out;
    }
}
