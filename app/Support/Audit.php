<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * One-line audit trail:  Audit::record('loan.disbursed', $loan, 'K 2,000 paid by bank transfer', ['ref' => 'TX123']);
 * Never throws — a failed audit write must not break the business action, but it is reported.
 */
class Audit
{
    public static function record(string $action, ?Model $subject = null, ?string $summary = null, array $changes = [], ?int $companyId = null): ?AuditLog
    {
        try {
            $user = auth()->user();

            return AuditLog::create([
                'company_id'   => $companyId ?? $subject?->getAttribute('company_id') ?? Tenancy::companyId(),
                'user_id'      => $user?->id,
                'user_name'    => $user?->name,
                'action'       => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id'   => $subject?->getKey(),
                'summary'      => $summary ? mb_substr($summary, 0, 500) : null,
                'changes'      => $changes ?: null,
                'ip'           => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
