<?php

namespace App\Mail;

use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayrollApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PayrollRun $run,
        public string $kind,          // review | sent_back | locked
        public User $recipient,
        public ?User $actor = null,
        public ?string $comment = null,
        public ?array $step = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $name = $this->run->displayName();

        return new Envelope(subject: match ($this->kind) {
            'review'    => "Please review: {$name}",
            'sent_back' => "Changes requested: {$name}",
            default     => "Approved: {$name}",
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payroll-approval');
    }
}
