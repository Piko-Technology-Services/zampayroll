<?php

namespace App\Http\Controllers;

use App\Jobs\SendPayslipEmail;
use App\Models\Payroll;
use App\Models\PayrollRun;
use App\Services\Payslips\PayslipDelivery;
use App\Support\Tenancy;

/**
 * Quick "email payslip" buttons. They use the company's default delivery choice
 * (work email, personal email or both); the Share payslips screen gives full control.
 */
class PayslipMailController extends Controller
{
    public function __construct(private PayslipDelivery $delivery)
    {
    }

    public function sendSingle(Payroll $payroll)
    {
        $payroll->load(['employee', 'run']);
        $s = $this->delivery->settings();
        $to = $this->delivery->recipients($payroll->employee, $s['work'], $s['personal'] || ! $s['work']);
        $name = trim($payroll->employee->first_name . ' ' . $payroll->employee->last_name);

        if (! $to) {
            return back()->with('error', "{$name} has no email address on file.");
        }

        SendPayslipEmail::dispatch($payroll->id, Tenancy::company()?->name ?? 'ZamPayroll', null, $to);
        $payroll->forceFill(['emailed_at' => now(), 'emailed_to' => implode(', ', $to)])->saveQuietly();

        return back()->with('success', "Payslip is being sent to " . implode(' and ', $to) . '.');
    }

    public function sendBulk(PayrollRun $run)
    {
        $payrolls = $run->payrolls()->with('employee')->get();
        if ($payrolls->isEmpty()) {
            return back()->with('error', 'No payslips found for this run.');
        }

        $s = $this->delivery->settings();
        try {
            $r = $this->delivery->share($run, $payrolls, ['portal' => false, 'work' => $s['work'], 'personal' => $s['personal'] || ! $s['work']]);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = "{$r['emailed']} payslip(s) queued for sending.";
        if ($r['no_email']) {
            $msg .= ' Skipped (no email on file): ' . implode(', ', $r['no_email']) . '.';
        }

        return back()->with($r['emailed'] > 0 ? 'success' : 'error', $msg);
    }
}
