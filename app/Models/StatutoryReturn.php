<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A NAPSA / NHIMA / ZRA return that HR edited before exporting. */
class StatutoryReturn extends Model
{
    use Concerns\BelongsToCompany;

    protected $fillable = ['company_id', 'kind', 'scope_key', 'payroll_run_id', 'period', 'rows', 'removed', 'edited_by', 'exported_at'];

    protected $casts = ['rows' => 'array', 'removed' => 'array', 'exported_at' => 'datetime'];

    public function editor()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
