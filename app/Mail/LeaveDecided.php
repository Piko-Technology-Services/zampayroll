<?php

namespace App\Mail;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeaveDecided extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LeaveRequest $leaveRequest)
    {
    }

    public function build()
    {
        $status = ucfirst($this->leaveRequest->status);

        return $this->subject("Your Leave Application Has Been {$status}")
            ->view('emails.leave-decided');
    }
}
