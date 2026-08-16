<?php

namespace App\Mail;

use App\Models\LoanRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoanDecided extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LoanRequest $loanRequest)
    {
    }

    public function build()
    {
        $status = ucfirst($this->loanRequest->status);

        return $this->subject("Your Loan Application Has Been {$status}")
            ->view('emails.loan-decided');
    }
}
