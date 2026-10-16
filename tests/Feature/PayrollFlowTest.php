<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\OvertimeRequest;
use App\Models\PayrollRun;
use App\Models\PayrollRunEvent;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollFlowTest extends TestCase
{
    private function admin(): User
    {
        Tenancy::reset(); DB::setDefaultConnection('mysql');
        return User::where('email', 'admin@pikozm.com')->first();
    }

    public function test_full_payroll_journey_with_approval_chain(): void
    {
        $admin = $this->admin();
        $cid = $admin->company_id;
        $pat = User::create(['company_id' => $cid, 'name' => 'Pat Payroll', 'email' => 'pat@pikozm.com', 'password' => bcrypt('x12345678'), 'role' => 'payroll', 'email_verified_at' => now()]);
        $this->actingAs($admin);

        // Admin sets a two-step chain
        $this->get('http://localhost/settings/payroll-approvals')->assertOk()->assertSee('Payroll approval chain');
        $this->put('http://localhost/settings/payroll-approvals', ['steps' => [
            ['name' => 'Payroll review', 'who' => 'role:payroll'],
            ['name' => 'Final approval', 'who' => 'role:company_admin'],
        ], 'self_approval' => 1])->assertRedirect()->assertSessionHas('success');
        $this->put('http://localhost/settings/payroll-approvals', ['steps' => [['name' => 'X', 'who' => 'nonsense']]])->assertSessionHasErrors('steps.0.who');

        // Data for the month
        $mary = Employee::where('first_name', 'Mary')->first();
        $john = Employee::where('first_name', 'John')->first();
        $mary->update(['net_salary' => 8000]);              // was 6,500 in September → big change
        $john->update(['net_salary' => 9000, 'bank_account_no' => null]);
        OvertimeRequest::create(['employee_id' => $john->id, 'company_email' => 'john@pikozm.com', 'date' => '2026-10-10', 'start_time' => '17:00', 'end_time' => '21:00', 'hours' => 4, 'type' => 'weekday', 'rate_multiplier' => 1.5, 'amount' => 500, 'status' => 'approved']);
        $loan = Loan::create(['employee_id' => $john->id, 'principal_amount' => 2000, 'balance' => 2000, 'payment_plan' => 'monthly', 'installment_amount' => 500, 'start_date' => '2026-10-01', 'next_deduction_date' => '2026-10-25', 'status' => 'active']);
        LeaveRequest::create(['employee_id' => $mary->id, 'leave_type' => 'annual', 'company_email' => 'mary@pikozm.com', 'start_date' => '2026-10-05', 'end_date' => '2026-10-07', 'days' => 3, 'status' => 'approved']);

        // Pat starts October
        $this->actingAs($pat);
        $this->get('http://localhost/payroll/runs/all')->assertOk()->assertSee('This month');
        $this->get('http://localhost/payroll/runs/create?month=2026-10')->assertOk()->assertSee('October payroll');
        $r = $this->post('http://localhost/payroll/runs', ['period' => '2026-10', 'include_overtime' => 1]);
        $run = PayrollRun::where('period', '2026-10')->first();
        $r->assertRedirect(route('payroll.runs.show', $run));
        $this->assertSame(PayrollRun::CALCULATED, $run->status);
        $this->assertSame(Employee::where('employment_status', 'Active')->count(), $run->payrolls()->count());
        $johnPay = $run->payrolls()->where('employee_id', $john->id)->first();
        $this->assertSame(1, $johnPay->items()->where('description', 'like', 'Overtime%')->count());
        $this->assertSame(1, $johnPay->items()->where('description', 'like', 'Loan Repayment%')->count());

        // Recalculating doesn't duplicate overtime or loans, and the loan isn't posted yet
        $this->post('http://localhost/payroll/runs/' . $run->id . '/generate')->assertRedirect();
        $this->assertSame(1, $johnPay->fresh()->items()->where('description', 'like', 'Loan Repayment%')->count());
        $this->assertSame(0, LoanPayment::where('loan_id', $loan->id)->count());

        $page = $this->get('http://localhost/payroll/runs/' . $run->id)->assertOk();
        foreach (['Big change from last month', 'No bank account', 'On leave', 'Overtime', 'Loan repayment', 'Submit for approval', 'Payroll review'] as $txt) {
            $page->assertSee($txt);
        }

        // Fixes need acknowledging
        $this->post('http://localhost/payroll/runs/' . $run->id . '/submit', [])->assertSessionHasErrors('acknowledged');
        $this->post('http://localhost/payroll/runs/' . $run->id . '/submit', ['acknowledged' => 1, 'comment' => 'Includes John\'s overtime'])->assertSessionHas('success');
        $run->refresh();
        $this->assertSame([PayrollRun::IN_REVIEW, 1], [$run->status, $run->current_step]);

        // Frozen while in review
        $this->post('http://localhost/payroll/runs/' . $run->id . '/generate')->assertSessionHas('error');
        $this->postJson('http://localhost/payroll/runs/' . $run->id . '/adjustments', ['employee_id' => $john->id, 'name' => 'Bonus', 'type' => 'earning', 'formula_type' => 'fixed', 'value' => 100, 'tax_profile' => 'taxable'])->assertStatus(423);

        // Admin isn't step 1
        $this->actingAs($admin)->post('http://localhost/payroll/runs/' . $run->id . '/approve')->assertSessionHas('error');
        $this->get('http://localhost/payroll/runs/' . $run->id)->assertOk()->assertSee('Waiting for Payroll review');

        // Pat approves step 1 → moves to step 2
        $this->actingAs($pat)->get('http://localhost/payroll/runs/' . $run->id)->assertSee('Review and approve');
        $this->post('http://localhost/payroll/runs/' . $run->id . '/approve', ['comment' => 'Checked'])->assertSessionHas('success');
        $this->assertSame(2, $run->fresh()->current_step);

        // Admin sends back (comment required), Pat resubmits, both approve
        $this->actingAs($admin)->post('http://localhost/payroll/runs/' . $run->id . '/send-back', [])->assertSessionHasErrors('comment');
        $this->post('http://localhost/payroll/runs/' . $run->id . '/send-back', ['comment' => 'Add John\'s bank details'])->assertSessionHas('success');
        $this->assertSame(PayrollRun::CALCULATED, $run->fresh()->status);
        $this->get('http://localhost/payroll/runs/' . $run->id)->assertSee('Add John&#039;s bank details', false);

        $this->actingAs($pat)->post('http://localhost/payroll/runs/' . $run->id . '/submit', ['acknowledged' => 1])->assertSessionHas('success');
        $this->post('http://localhost/payroll/runs/' . $run->id . '/approve')->assertSessionHas('success');
        $this->actingAs($admin)->post('http://localhost/payroll/runs/' . $run->id . '/approve', ['comment' => 'OK to pay'])->assertSessionHas('success');

        $run->refresh();
        $this->assertSame(PayrollRun::APPROVED, $run->status);
        $this->assertTrue($run->isLocked());
        $this->assertSame(0, $run->payrolls()->where('status', '!=', 'Approved')->count());
        $this->assertSame(1, LoanPayment::where('loan_id', $loan->id)->count());
        $this->assertEquals(1500, (float) $loan->fresh()->balance);
        $this->assertSame(['submitted', 'approved', 'sent_back', 'submitted', 'approved', 'approved', 'locked'],
            PayrollRunEvent::where('payroll_run_id', $run->id)->where('action', '!=', 'calculated')->orderBy('id')->pluck('action')->all());

        // Locked: no edits, no trash; can be marked paid
        $this->delete('http://localhost/payroll/runs/' . $run->id . '/trash')->assertSessionHas('error');
        $this->get('http://localhost/payroll/runs/' . $run->id)->assertSee('Approved and locked')->assertSee('Mark as paid');
        $this->post('http://localhost/payroll/runs/' . $run->id . '/paid')->assertSessionHas('success');
        $this->assertSame(PayrollRun::PAID, $run->fresh()->status);

        // The old one-click finalise no longer bypasses approvals
        $this->post('http://localhost/payroll/runs/' . $run->id . '/finalize')->assertRedirect(route('payroll.runs.show', $run));

        // Other companies can't see it
        $this->actingAs(User::where('email', 'admin@second.com')->first())->get('http://localhost/payroll/runs/' . $run->id)->assertNotFound();

        $pat->delete();
        $this->cleanup();
    }

    /** Leave the shared test database as other suites expect it. */
    private function cleanup(): void
    {
        Tenancy::reset();
        foreach (PayrollRun::withoutGlobalScopes()->withTrashed()->whereIn('period', ['2026-10', '2026-11'])->get() as $run) {
            foreach ($run->payrolls()->withoutGlobalScopes()->get() as $p) {
                $p->items()->withoutGlobalScopes()->delete();
                \App\Models\LoanPayment::withoutGlobalScopes()->where('payroll_id', $p->id)->delete();
                $p->delete();
            }
            \App\Models\PayrollRunAdjustment::withoutGlobalScopes()->where('payroll_run_id', $run->id)->delete();
            PayrollRunEvent::withoutGlobalScopes()->where('payroll_run_id', $run->id)->delete();
            $run->forceDelete();
        }
        \App\Models\PayrollApprovalStep::withoutGlobalScopes()->delete();
        DB::table('companies')->update(['payroll_allow_self_approval' => 1]);
    }

    public function test_preparer_cannot_self_approve_when_switched_off_and_can_recall(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->put('http://localhost/settings/payroll-approvals', ['steps' => [['name' => 'Approval', 'who' => 'role:company_admin']], 'self_approval' => 0])->assertSessionHas('success');
        Employee::query()->update(['net_salary' => 5000]);

        $this->post('http://localhost/payroll/runs', ['period' => '2026-11', 'include_overtime' => 0])->assertRedirect();
        $run = PayrollRun::where('period', '2026-11')->first();
        $this->assertFalse($run->include_overtime);
        $this->post('http://localhost/payroll/runs/' . $run->id . '/submit', ['acknowledged' => 1])->assertSessionHas('success');
        $this->post('http://localhost/payroll/runs/' . $run->id . '/approve')->assertSessionHas('error', 'You prepared this payroll, so someone else has to approve it.');
        $this->post('http://localhost/payroll/runs/' . $run->id . '/recall')->assertSessionHas('success');
        $this->assertTrue($run->fresh()->isEditable());

        // AI mode respects the lock too
        $this->post('http://localhost/payroll/runs/' . $run->id . '/submit', ['acknowledged' => 1]);
        try {
            app(\App\Services\HrActions::class)->preparePayroll('2026-11', null, $admin);
            $this->fail('A payroll in approval must not be recalculated.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('waiting for approval', $e->getMessage());
        }
        $this->cleanup();
    }
}
