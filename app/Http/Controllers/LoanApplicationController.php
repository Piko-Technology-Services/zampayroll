<?php

namespace App\Http\Controllers;

use App\Mail\LoanApplied;
use App\Models\Employee;
use App\Models\LoanRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class LoanApplicationController extends Controller
{
    public function form()
    {
        return view('loan.apply');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'email'              => 'required|email',
            'amount'             => 'required|numeric|min:1',
            'payment_plan'       => 'required|in:' . implode(',', array_keys(LoanRequest::PAYMENT_PLANS)),
            'payment_plan_note'  => 'nullable|string|max:255',
            'reason'             => 'nullable|string|max:1000',
            'documents.*'        => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
        ]);

        $employee = Employee::where('employment_status', 'Active')
            ->where('company_email', $validated['email'])
            ->first();

        if (! $employee) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'This email address doesn\'t match any employee record. Please use your registered company email address.']);
        }

        $documents = [];
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $path = $file->store('uploads/loans/documents', 'public');
                $documents[] = [
                    'name'        => $file->getClientOriginalName(),
                    'path'        => $path,
                    'uploaded_at' => now()->toDateTimeString(),
                ];
            }
        }

        $loanRequest = LoanRequest::create([
            'employee_id'        => $employee->id,
            'company_email'      => $validated['email'],
            'amount'             => $validated['amount'],
            'payment_plan'       => $validated['payment_plan'],
            'payment_plan_note'  => $validated['payment_plan_note'] ?? null,
            'reason'             => $validated['reason'] ?? null,
            'documents'          => $documents ?: null,
            'status'             => 'pending',
        ]);

        $this->notifyHr($loanRequest);

        return view('loan.applied', ['loanRequest' => $loanRequest]);
    }

    private function notifyHr(LoanRequest $loanRequest): void
    {
        $employee = $loanRequest->employee;

        $adminEmails = User::where('company_id', $employee->company_id)
            ->where('role', 'company_admin')
            ->pluck('email')
            ->toArray();

        if (empty($adminEmails)) {
            return;
        }

        try {
            Mail::to($adminEmails)->send(new LoanApplied($loanRequest));
        } catch (\Exception $e) {
            report($e);
        }
    }
}
