<?php

namespace App\Mail;

use App\Models\LoanRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoanApplied extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LoanRequest $loanRequest)
    {
    }

    public function build()
    {
        return $this->subject('New Loan Application — ' . $this->loanRequest->employee->first_name . ' ' . $this->loanRequest->employee->last_name)
            ->view('emails.loan-applied');
    }
}