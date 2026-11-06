<?php

namespace App\Services\Compliance;

use App\Mail\ComplianceMail;
use App\Models\Compliance\ComplianceMailLog;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;

/**
 * Sends Compliance Centre emails through the existing mail setup and records every attempt
 * (sent or failed) in compliance_mail_logs. A failed email never breaks the action that caused it.
 */
class ComplianceMailer
{
    /**
     * @param iterable<string|User|null> $to
     * @param array{subject:string, eyebrow:string, heading:string, intro:string, rows?:array, url?:string, label?:string, attachment?:string, attachment_name?:string} $m
     */
    public static function send(string $event, iterable $to, array $m, ?string $subjectType = null, ?int $subjectId = null): array
    {
        $emails = collect($to)->map(fn ($r) => $r instanceof User ? $r->email : $r)
            ->filter(fn ($e) => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL))->map(fn ($e) => strtolower($e))->unique()->values();
        $result = ['sent' => 0, 'failed' => 0];

        foreach ($emails as $email) {
            $status = 'sent';
            $error = null;
            try {
                Mail::to($email)->send(new ComplianceMail($event, $m['subject'], $m['eyebrow'], $m['heading'], $m['intro'], $m['rows'] ?? [],
                    $m['url'] ?? null, $m['label'] ?? null, $m['attachment'] ?? null, $m['attachment_name'] ?? null));
                $result['sent']++;
            } catch (\Throwable $e) {
                report($e);
                $status = 'failed';
                $error = mb_substr($e->getMessage(), 0, 1000);
                $result['failed']++;
            }
            try {
                ComplianceMailLog::create(['company_id' => Tenancy::companyId(), 'subject_type' => $subjectType, 'subject_id' => $subjectId,
                    'event' => $event, 'recipient' => $email, 'status' => $status, 'error' => $error]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $result;
    }

    /** Staff of the current company who hold a permission (for "notify HR" style messages). */
    public static function staffWith(string $permission)
    {
        return User::where('company_id', Tenancy::companyId())->get()->filter(fn (User $u) => $u->isStaff() && $u->hasPermission($permission))->values();
    }
}
