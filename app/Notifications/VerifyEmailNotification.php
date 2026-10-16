<?php

namespace App\Notifications;

use App\Support\Tenancy;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class VerifyEmailNotification extends Notification
{
    public const EXPIRES_MINUTES = 120;

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(self::EXPIRES_MINUTES), [
            'id'   => $notifiable->getKey(),
            'hash' => sha1(Str::lower($notifiable->getEmailForVerification())),
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        $company = $notifiable->company;

        return (new MailMessage)
            ->subject('Confirm your email to activate ZamPayroll ✉️')
            ->view('emails.auth.verify-email', [
                'url'         => $this->verificationUrl($notifiable),
                'firstName'   => Str::before($notifiable->name, ' ') ?: $notifiable->name,
                'email'       => $notifiable->email,
                'companyName' => $company?->name ?? 'your company',
                'dedicated'   => (bool) $company?->isDedicated(),
                'host'        => $company?->workspaceHost(),
                'limit'       => config('tenancy.shared_employee_limit'),
                'expires'     => self::EXPIRES_MINUTES,
                'companyCode' => $company?->isDedicated() ? null : $company?->company_code,
            ]);
    }
}
