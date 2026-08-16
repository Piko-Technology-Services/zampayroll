<?php

namespace App\Mail;

use App\Models\OvertimeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OvertimeDecided extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OvertimeRequest $overtimeRequest)
    {
    }

    public function build()
    {
        $status = ucfirst($this->overtimeRequest->status);

        return $this->subject("Your Overtime Application Has Been {$status}")
            ->view('emails.overtime-decided');
    }
}
