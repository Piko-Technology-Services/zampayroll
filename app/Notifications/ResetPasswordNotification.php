<?php

namespace App\Notifications;

use App\Support\Tenancy;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        // route() uses the current root URL — on a dedicated workspace (or inside
        // Tenancy::runInDedicated) that is the company's own sub-domain.
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);

        $expires = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

        return (new MailMessage)
            ->subject('Reset your ZamPayroll password 🔐')
            ->view('emails.auth.reset-password', [
                'url'       => $url,
                'firstName' => Str::before($notifiable->name, ' ') ?: $notifiable->name,
                'email'     => $notifiable->email,
                'workspace' => Tenancy::dedicatedCompany()?->workspaceHost(),
                'companyName' => $notifiable->company?->name,
                'expires'   => $expires,
            ]);
    }
}
