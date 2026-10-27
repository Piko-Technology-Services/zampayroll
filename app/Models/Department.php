<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A department HR manages. Employees store the department's name
 * (employees.department), so renaming a department renames it on every employee.
 */
class Department extends Model
{
    use Concerns\BelongsToCompany;

    public const PALETTE = ['#00742D', '#C8742B', '#2C6E9B', '#7A4E9C', '#B8892B', '#1F8A7A', '#A0473E', '#4F6B2F'];

    protected $fillable = ['company_id', 'name', 'description', 'color', 'head_employee_id'];

    public function head()
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
    }

    public function employees()
    {
        return Employee::query()->where('department', $this->name);
    }

    /** Make sure a department with this name exists for the current company; returns it (or null for blank). */
    public static function ensure(?string $name, ?int $companyId = null): ?self
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));
        $companyId ??= \App\Support\Tenancy::companyId();
        if ($name === '' || ! $companyId) {
            return null;
        }

        $q = fn () => static::query()->forCompany($companyId);
        $existing = $q()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if ($existing) {
            return $existing;
        }

        $dept = new static(['name' => $name, 'color' => self::PALETTE[$q()->count() % count(self::PALETTE)]]);
        $dept->company_id = $companyId;
        $dept->save();

        return $dept;
    }

    /** All department names for the current company: managed ones plus any still only on employees. */
    public static function names(): \Illuminate\Support\Collection
    {
        return static::query()->orderBy('name')->pluck('name')
            ->merge(Employee::query()->whereNotNull('department')->where('department', '!=', '')->distinct()->pluck('department'))
            ->map(fn ($n) => trim($n))->filter()->unique(fn ($n) => mb_strtolower($n))->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /** name => colour for every department (deterministic colour for unmanaged names). */
    public static function colours(): array
    {
        $map = static::query()->pluck('color', 'name')->all();
        foreach (static::names() as $n) {
            $map[$n] ??= self::PALETTE[abs(crc32(mb_strtolower($n))) % count(self::PALETTE)];
        }

        return $map;
    }
}
