<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Letterhead extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $table = 'company_letterheads';

    protected $guarded = ['id', 'company_id'];

    protected $casts = ['settings' => 'array', 'is_default' => 'boolean'];

    public const DEFAULTS = [
        'margin_top' => 20, 'margin_right' => 20, 'margin_bottom' => 20, 'margin_left' => 20, // mm, around the body
        'header_height' => 32, 'footer_height' => 16,                                         // mm
        'logo_position' => 'left', 'header_align' => 'left',
        'page_numbers' => true, 'font' => 'DejaVu Sans', 'font_size' => 10.5, 'accent' => '#0B3D2E',
        'show_registration' => true,
    ];

    public const FONTS = ['DejaVu Sans' => 'Sans (DejaVu Sans)', 'DejaVu Serif' => 'Serif (DejaVu Serif)', 'Helvetica' => 'Helvetica', 'Times' => 'Times'];

    public function setting(string $key)
    {
        return ($this->settings ?? [])[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    public function allSettings(): array
    {
        return array_merge(self::DEFAULTS, $this->settings ?? []);
    }

    /** The company's default letterhead, or null when none has been set up. */
    public static function defaultForCompany(): ?self
    {
        return static::orderByDesc('is_default')->orderBy('id')->first();
    }
}
