<?php

namespace App\Mail;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeaveApplied extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public LeaveRequest $leaveRequest)
    {
    }

    public function build()
    {
        return $this->subject('New Leave Application — ' . $this->leaveRequest->employee->first_name . ' ' . $this->leaveRequest->employee->last_name)
            ->view('emails.leave-applied');
    }
}
