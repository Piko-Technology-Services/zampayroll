<?php

namespace App\Mail;

use App\Models\OvertimeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OvertimeApplied extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OvertimeRequest $overtimeRequest)
    {
    }

    public function build()
    {
        return $this->subject('New Overtime Application — ' . $this->overtimeRequest->employee->first_name . ' ' . $this->overtimeRequest->employee->last_name)
            ->view('emails.overtime-applied');
    }
}
