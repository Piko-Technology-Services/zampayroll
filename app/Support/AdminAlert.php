<?php

namespace App\Support;

use App\Mail\AdminAlertMail;
use Illuminate\Support\Facades\Mail;

/** Emails the ZamPayroll team (config site.admin_alert_emails) about events they should know about. Never breaks the user's request. */
class AdminAlert
{
    /** @return list<string> */
    public static function recipients(): array
    {
        return array_values(array_filter(array_map('trim', (array) config('site.admin_alert_emails', []))));
    }

    /** @param array<string, string|null> $rows */
    public static function send(string $subject, string $eyebrow, string $heading, string $intro, array $rows = [], ?string $url = null, ?string $label = null): void
    {
        $to = self::recipients();
        if (! $to) {
            return;
        }

        try {
            Mail::to($to)->send(new AdminAlertMail($subject, $eyebrow, $heading, $intro, $rows, $url, $label));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
