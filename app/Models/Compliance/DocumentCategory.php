<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class DocumentCategory extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected $casts = ['is_default' => 'boolean'];

    /** Suggested starting categories. Organisational only: not a statement that each document is legally required. */
    public const DEFAULTS = [
        'Employment Documentation' => ['Employment contracts', 'Contract amendments', 'Job descriptions', 'Appointment letters', 'Termination letters', 'Employee declarations'],
        'Company Policies' => ['Disciplinary policies', 'Grievance policies', 'Health and safety policies', 'Leave policies', 'Code of conduct', 'Other workplace policies'],
        'Payroll & Statutory Records' => ['Payroll reports', 'Payslip records', 'PAYE documentation', 'NAPSA records', 'NHIMA records', 'Other statutory documents'],
        'HR & Workforce Records' => ['HR manuals', 'Recruitment documents', 'Training records', 'Performance reviews', 'Staff registers', 'Employee handbooks'],
        'Health & Safety' => ['Safety procedures', 'Risk assessments', 'Workplace inspection records', 'Incident reports', 'Safety certificates'],
        'Company & Legal Documents' => ['Business registrations', 'Licences', 'Certificates', 'Internal procedures', 'Other company records'],
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('name');
    }

    public function fullName(): string
    {
        return $this->parent ? $this->parent->name . ' › ' . $this->name : $this->name;
    }

    /** Create the suggested categories the first time a company opens the repository. */
    public static function ensureDefaults(): void
    {
        if (static::exists()) {
            return;
        }
        $i = 0;
        foreach (self::DEFAULTS as $parent => $children) {
            $p = static::create(['name' => $parent, 'is_default' => true, 'sort' => $i++]);
            foreach ($children as $j => $child) {
                static::create(['name' => $child, 'parent_id' => $p->id, 'is_default' => true, 'sort' => $j]);
            }
        }
    }
}
