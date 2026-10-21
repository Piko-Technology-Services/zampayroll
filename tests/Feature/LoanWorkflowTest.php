<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanRequest;
use App\Models\Payroll;
use App\Models\PayrollRun;
use App\Models\PayrollRunAdjustment;
use App\Models\User;
use App\Services\HrActions;
use App\Services\LoanRepaymentService;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Loans: approval and payout are separate; nothing is deducted before payout;
 * one repayment per month even with two payroll runs.
 */
class LoanWorkflowTest extends TestCase
{
    private User $admin;
    private User $hr;
    private User $payroll;
    private Employee $mary;
    private User $maryUser;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Tenancy::reset();
        DB::setDefaultConnection('mysql');
        $this->admin = User::where('email', 'admin@pikozm.com')->first();
        $cid = $this->admin->company_id;
        Tenancy::setCompanyId($cid);
        $this->mary = Employee::where('employee_id', 'EMP-001')->first();
        $this->mary->forceFill(['net_salary' => 6500, 'salary' => 8000])->save();
        $mk = fn ($email, $role, $extra = []) => User::firstOrCreate(['email' => $email], ['company_id' => $cid, 'name' => ucfirst(strtok($email, '@')) . ' User',
            'password' => bcrypt('x12345678'), 'role' => $role, 'email_verified_at' => now()] + $extra);
        $this->hr = $mk('hr.loans@pikozm.com', 'hr');
        $this->payroll = $mk('pay.loans@pikozm.com', 'payroll');
        $this->maryUser = $mk('mary.loans@pikozm.com', 'employee', ['employee_id' => $this->mary->id]);
        // This company lets HR approve loans (but not record payouts).
        $this->hrPerms = DB::table('company_roles')->where('company_id', $cid)->where('key', 'hr')->value('permissions');
        DB::table('company_roles')->where('company_id', $cid)->where('key', 'hr')
            ->update(['permissions' => json_encode(array_values(array_unique(array_merge(json_decode($this->hrPerms ?? '[]', true) ?: [], ['loans.manage']))))]);
        $this->clean();
    }

    private ?string $hrPerms = null;

    private function clean(): void
    {
        foreach (PayrollRun::withTrashed()->whereIn('period', ['2026-05', '2026-06'])->get() as $run) {
            PayrollRunAdjustment::where('payroll_run_id', $run->id)->delete();
            foreach ($run->payrolls()->get() as $p) {
                LoanPayment::where('payroll_id', $p->id)->delete();
                $p->items()->delete();
                $p->delete();
            }
            DB::table('payroll_run_events')->where('payroll_run_id', $run->id)->delete();
            $run->forceDelete();
        }
        $loanIds = Loan::where('employee_id', $this->mary->id)->pluck('id');
        LoanPayment::whereIn('loan_id', $loanIds)->delete();
        Loan::whereIn('id', $loanIds)->delete();
        LoanRequest::where('employee_id', $this->mary->id)->delete();
        Loan::syncEmployeeCache($this->mary->id);
    }

    public function test_request_approve_payout_in_separate_recorded_steps(): void
    {
        // Employee asks.
        $this->actingAs($this->maryUser)->post('http://localhost/portal/loans', ['amount' => 3000, 'payment_plan' => 'monthly', 'installment_amount' => 1000, 'reason' => 'School fees'])
            ->assertSessionHas('success');
        $req = LoanRequest::where('employee_id', $this->mary->id)->latest('id')->first();
        // Only one open request at a time.
        $this->actingAs($this->maryUser)->post('http://localhost/portal/loans', ['amount' => 500, 'payment_plan' => 'once_off'])->assertSessionHasErrors('amount');

        // HR approves with changed terms → ledger entry, not paid out, nothing deductible.
        $this->actingAs($this->hr)->post("http://localhost/loan/dashboard/requests/{$req->id}/approve", ['amount' => 2500, 'installment_amount' => 500, 'first_deduction' => now()->addMonthNoOverflow()->format('Y-m')])
            ->assertSessionHas('success');
        $this->assertSame('approved', $req->fresh()->status);
        $loan = Loan::where('loan_request_id', $req->id)->first();
        $this->assertSame('pending_disbursement', $loan->status);
        $this->assertEquals(2500, (float) $loan->principal_amount);
        $this->assertFalse($loan->isDeductible());
        // Approving again does nothing.
        $this->actingAs($this->hr)->post("http://localhost/loan/dashboard/requests/{$req->id}/approve")->assertSessionHasErrors('loan');
        $this->assertSame(1, Loan::where('loan_request_id', $req->id)->count());

        // HR has no payout permission; payroll officer records it.
        $this->actingAs($this->hr)->post("http://localhost/loan/loans/{$loan->id}/disburse", ['date' => today()->toDateString(), 'method' => 'bank_transfer'])->assertForbidden();
        $this->actingAs($this->payroll)->post("http://localhost/loan/loans/{$loan->id}/disburse", ['date' => today()->addDay()->toDateString(), 'method' => 'bank_transfer'])
            ->assertSessionHasErrors('date');
        $this->actingAs($this->payroll)->post("http://localhost/loan/loans/{$loan->id}/disburse", ['date' => today()->toDateString(), 'method' => 'mobile_money', 'reference' => 'MP2610.1234'])
            ->assertSessionHas('success');
        $loan->refresh();
        $this->assertSame('active', $loan->status);
        $this->assertSame('MP2610.1234', $loan->disbursement_reference);
        $this->assertTrue($loan->isDeductible());
        $this->assertEquals(2500, (float) $this->mary->fresh()->total_loan_balance);
        // Paying out twice is refused.
        $this->actingAs($this->payroll)->post("http://localhost/loan/loans/{$loan->id}/disburse", ['date' => today()->toDateString(), 'method' => 'cash'])->assertSessionHasErrors('loan');

        foreach (['loan.applied', 'loan.approved', 'loan.disbursed'] as $action) {
            $this->assertTrue(AuditLog::where('action', $action)->exists(), $action);
        }

        // Portal shows the plan; status can't be forced back to "not paid out" from the ledger form.
        $this->actingAs($this->maryUser)->get('http://localhost/portal/loans')->assertOk()->assertSee('Repayment plan');
        $this->actingAs($this->hr)->put("http://localhost/loan/ledger/{$loan->id}", ['payment_plan' => 'monthly', 'installment_amount' => 500, 'status' => 'pending_disbursement'])->assertSessionHasErrors('status');
    }

    public function test_no_deductions_before_payout_and_one_per_month(): void
    {
        $hr = app(HrActions::class);
        $loan = Loan::create(['employee_id' => $this->mary->id, 'principal_amount' => 1200, 'balance' => 1200, 'payment_plan' => 'monthly', 'installment_amount' => 400,
            'start_date' => '2026-05-01', 'next_deduction_date' => '2026-05-01', 'status' => 'pending_disbursement', 'approved_by' => $this->hr->id, 'approved_at' => now()]);

        // Not paid out → May payroll has no repayment.
        $may = $hr->preparePayroll('2026-05', null, $this->admin);
        $this->assertSame(0, PayrollRunAdjustment::where('payroll_run_id', $may->id)->where('loan_id', $loan->id)->count());

        // Paid out (recorded on the ledger directly, back-dated) → recalculating May adds one repayment.
        $loan->forceFill(['status' => 'active', 'disbursed_at' => '2026-04-28', 'disbursed_by' => $this->payroll->id, 'disbursement_method' => 'cash'])->save();
        $hr->generatePayroll($may, $this->admin);
        $this->assertSame(1, PayrollRunAdjustment::where('payroll_run_id', $may->id)->where('loan_id', $loan->id)->count());

        // A second May run doesn't deduct it again.
        $extra = PayrollRun::create(['period' => '2026-05', 'alias' => 'May extra', 'status' => PayrollRun::DRAFT]);
        $hr->generatePayroll($extra, $this->admin);
        $this->assertSame(0, PayrollRunAdjustment::where('payroll_run_id', $extra->id)->where('loan_id', $loan->id)->count());

        // Lock May: one repayment posted to the ledger.
        $repay = app(LoanRepaymentService::class);
        foreach ($may->payrolls()->get() as $p) {
            $repay->recordDeductions($p);
            $repay->recordDeductions($p); // twice is harmless
        }
        $this->assertSame(1, LoanPayment::where('loan_id', $loan->id)->where('type', 'deduction')->count());
        $this->assertEquals(800, (float) $loan->fresh()->balance);
        // Even if the extra run had a stray line and got locked, it would not post a second May repayment.
        foreach ($extra->payrolls()->get() as $p) {
            $repay->recordDeductions($p);
        }
        $this->assertSame(1, LoanPayment::where('loan_id', $loan->id)->where('type', 'deduction')->count());

        // Paused loans aren't deducted in June.
        $loan->update(['status' => 'paused']);
        $june = $hr->preparePayroll('2026-06', null, $this->admin);
        $this->assertSame(0, PayrollRunAdjustment::where('payroll_run_id', $june->id)->where('loan_id', $loan->id)->count());
    }

    public function test_cancel_before_payout_and_ledger_entry_with_payout(): void
    {
        $this->actingAs($this->maryUser)->post('http://localhost/portal/loans', ['amount' => 900, 'payment_plan' => 'once_off'])->assertSessionHas('success');
        $req = LoanRequest::where('employee_id', $this->mary->id)->latest('id')->first();
        $this->actingAs($this->maryUser)->delete("http://localhost/portal/loans/{$req->id}")->assertSessionHas('success');
        $this->assertSame('cancelled', $req->fresh()->status); // kept, not deleted

        // Approved, then cancelled before payout.
        $this->actingAs($this->maryUser)->post('http://localhost/portal/loans', ['amount' => 700, 'payment_plan' => 'once_off'])->assertSessionHas('success');
        $req2 = LoanRequest::where('employee_id', $this->mary->id)->latest('id')->first();
        $this->actingAs($this->admin)->post("http://localhost/loan/dashboard/requests/{$req2->id}/approve")->assertSessionHas('success');
        $loan = Loan::where('loan_request_id', $req2->id)->first();
        $this->actingAs($this->admin)->post("http://localhost/loan/loans/{$loan->id}/cancel", ['reason' => 'No longer needed'])->assertSessionHas('success');
        $this->assertSame('cancelled', $loan->fresh()->status);
        $this->assertSame('cancelled', $req2->fresh()->status);

        // HR without payout permission adds a loan → waits for payout; "already paid" needs permission.
        $this->actingAs($this->hr)->post('http://localhost/loan/ledger', ['employee_id' => $this->mary->id, 'principal_amount' => 1000, 'payment_plan' => 'monthly', 'installment_amount' => 250, 'start_date' => now()->toDateString()])
            ->assertRedirect();
        $this->assertSame('pending_disbursement', Loan::where('employee_id', $this->mary->id)->latest('id')->first()->status);
        $this->actingAs($this->hr)->post('http://localhost/loan/ledger', ['employee_id' => $this->mary->id, 'principal_amount' => 1000, 'payment_plan' => 'monthly', 'installment_amount' => 250, 'start_date' => now()->toDateString(), 'paid_out' => 1, 'disbursed_at' => now()->toDateString(), 'disbursement_method' => 'cash'])
            ->assertForbidden();
        $this->actingAs($this->admin)->post('http://localhost/loan/ledger', ['employee_id' => $this->mary->id, 'principal_amount' => 1000, 'payment_plan' => 'monthly', 'installment_amount' => 250, 'start_date' => now()->toDateString(), 'paid_out' => 1, 'disbursed_at' => now()->toDateString(), 'disbursement_method' => 'cash'])
            ->assertRedirect();
        $this->assertSame('active', Loan::where('employee_id', $this->mary->id)->latest('id')->first()->status);

        // The approver who isn't an admin can't record their own payout.
        $l = Loan::create(['employee_id' => $this->mary->id, 'principal_amount' => 300, 'balance' => 300, 'payment_plan' => 'once_off', 'start_date' => now()->toDateString(), 'status' => 'pending_disbursement', 'approved_by' => $this->payroll->id]);
        $this->actingAs($this->payroll)->post("http://localhost/loan/loans/{$l->id}/disburse", ['date' => today()->toDateString(), 'method' => 'cash'])->assertSessionHasErrors('loan');

        $this->actingAs($this->admin)->get('http://localhost/loan/dashboard/requests?status=all')->assertOk()->assertSee('Waiting to be paid out');
        $this->actingAs($this->admin)->get("http://localhost/loan/ledger/{$l->id}")->assertOk()->assertSee('Not paid out yet');
    }

    protected function tearDown(): void
    {
        Tenancy::setCompanyId($this->admin->company_id);
        $this->clean();
        DB::table('company_roles')->where('company_id', $this->admin->company_id)->where('key', 'hr')->update(['permissions' => $this->hrPerms]);
        User::whereIn('email', ['hr.loans@pikozm.com', 'pay.loans@pikozm.com', 'mary.loans@pikozm.com'])->delete();
        Tenancy::reset();
        parent::tearDown();
    }
}
