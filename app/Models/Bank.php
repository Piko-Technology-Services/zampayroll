<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $fillable = [
        'name',
        'short_name',
        'swift_code',
        'sort_code',
        'aliases',
        'is_active',
    ];

    protected $casts = [
        'aliases' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Match a free-text bank name (as typed on an employee record) against this
     * directory. Matches on name, short_name, or any alias, case-insensitively,
     * in either direction (so "Barclays Zambia" matches an alias of "Barclays").
     * Returns null if nothing matches confidently — callers should fall back to
     * asking HR to fill the SWIFT code in manually rather than guessing.
     */
    public static function findMatch(?string $bankName): ?self
    {
        if (!$bankName || trim($bankName) === '') {
            return null;
        }

        $needle = strtolower(trim($bankName));

        return static::active()->get()->first(function (Bank $bank) use ($needle) {
            $candidates = array_merge(
                [$bank->name, $bank->short_name],
                $bank->aliases ?? []
            );

            foreach (array_filter($candidates) as $candidate) {
                $candidate = strtolower($candidate);
                if ($candidate === $needle || str_contains($needle, $candidate) || str_contains($candidate, $needle)) {
                    return true;
                }
            }

            return false;
        });
    }
}
