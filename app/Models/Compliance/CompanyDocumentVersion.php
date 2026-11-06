<?php

namespace App\Models\Compliance;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class CompanyDocumentVersion extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'company_id'];

    public function previewable(): bool
    {
        return in_array($this->extension, ['pdf', 'png', 'jpg', 'jpeg'], true);
    }

    public function humanSize(): string
    {
        $b = (int) $this->size;

        return $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
    }
}
