<?php

namespace App\Models\Compliance;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Masters (company_id NULL) are visible to every company and read-only; company copies belong to one company.
 * Not using BelongsToCompany on purpose: every query must go through visible() / owned().
 */
class DocumentTemplate extends Model
{
    use SoftDeletes;

    protected $guarded = ['id', 'company_id'];

    protected $casts = ['is_active' => 'boolean', 'reviewed_on' => 'date'];

    protected static function booted(): void
    {
        // Route-model binding and plain queries only ever see masters + the current company's own templates.
        static::addGlobalScope('visible', function (Builder $q) {
            if (Tenancy::scopeDisabled()) {
                return;
            }
            $cid = Tenancy::companyId();
            $q->where(fn ($w) => $w->whereNull($q->getModel()->qualifyColumn('company_id'))
                ->when($cid, fn ($x) => $x->orWhere($q->getModel()->qualifyColumn('company_id'), $cid)));
        });
    }

    public function isMaster(): bool
    {
        return $this->company_id === null;
    }

    public function isOwnedByCurrentCompany(): bool
    {
        return $this->company_id !== null && (int) $this->company_id === (int) Tenancy::companyId();
    }

    public function versions()
    {
        return $this->hasMany(DocumentTemplateVersion::class, 'template_id')->orderByDesc('version');
    }

    public function source()
    {
        return $this->belongsTo(self::class, 'source_template_id');
    }

    /** A company copy whose master has moved on. */
    public function masterUpdateAvailable(): bool
    {
        return ! $this->isMaster() && $this->source && $this->source_version && $this->source->version > $this->source_version;
    }

    public function scopeKind(Builder $q, string $kind): Builder
    {
        return $q->where('kind', $kind);
    }
}
