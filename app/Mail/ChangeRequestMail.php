<?php

namespace App\Mail;

use App\Models\EmployeeChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** kind: requested (to the employee, as a security notice) | hr (to HR) | decided (to the employee) */
class ChangeRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public EmployeeChangeRequest $change, public string $kind)
    {
    }

    public function build()
    {
        $who = $this->change->employee?->full_name;

        return $this->subject(match ($this->kind) {
            'requested' => 'We received a request to change your bank details',
            'hr' => "Bank details change to check — {$who}",
            default => 'Your bank details change was ' . $this->change->status,
        })->view('emails.change-request');
    }
}
