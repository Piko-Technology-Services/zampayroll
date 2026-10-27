<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Payroll;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\PayrollApprovals;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Several pay runs in one month (everyone / departments / picked people), tracked by status, and unlocking an approved run. */
class PayRunsTest extends TestCase
{
    private User $admin;
    private Employee $mary;
    private Loan $loan;
    private array $maryWas = [];

    protected function setUp(): void
    {
        parent::setUp();
        Tenancy::reset();
        DB::setDefaultConnection('mysql');
        $this->admin = User::where('email', 'admin@pikozm.com')->first();
        Tenancy::setCompanyId($this->admin->company_id);
        $this->mary = Employee::where('employee_id', 'EMP-001')->first();
        $this->maryWas = $this->mary->only(['net_salary', 'department']);
        $this->mary->forceFill(['net_salary' => 6500, 'department' => 'Finance'])->save();
        $this->wipe();
        $this->loan = Loan::create(['employee_id' => $this->mary->id, 'principal_amount' => 1200, 'balance' => 1200, 'payment_plan' => 'monthly', 'installment_amount' => 400,
            'start_date' => '2026-04-01', 'next_deduction_date' => '2026-04-20', 'status' => 'active', 'disbursed_at' => '2026-04-01']);
    }

    protected function tearDown(): void
    {
        $this->wipe();
        LoanPayment::where('loan_id', $this->loan->id)->delete();
        $this->loan->delete();
        Loan::syncEmployeeCache($this->mary->id);
        $this->mary->forceFill($this->maryWas)->save();
        parent::tearDown();
    }

    private function wipe(): void
    {
        PayrollRun::withTrashed()->where('period', '2026-04')->get()->each(function ($r) {
            $r->payrolls()->each(fn ($p) => $p->items()->delete());
            $r->payrolls()->delete();
            $r->adjustments()->delete();
            $r->events()->delete();
            $r->forceDelete();
        });
    }

    private function lock(PayrollRun $run): void
    {
        $approvals = app(PayrollApprovals::class);
        (fn () => $this->lock($run, auth()->user()))->call($approvals);
    }

    public function test_several_runs_per_month_and_unlock(): void
    {
        $this->actingAs($this->admin);
        $active = Employee::where('employment_status', 'Active')->count();

        // Main run for everyone → approve & lock → loan repayment posted
        $this->post('http://localhost/payroll/runs', ['period' => '2026-04', 'include_overtime' => 1, 'coverage' => 'all', 'run_type' => 'regular'])->assertRedirect();
        $main = PayrollRun::where('period', '2026-04')->firstOrFail();
        $this->assertSame($active, $main->payrolls()->count());
        $this->assertSame('April 2026 payroll', $main->alias);
        $this->lock($main->fresh());
        $main->refresh();
        $this->assertTrue($main->isLocked());
        $this->assertEquals(800, (float) $this->loan->fresh()->balance);
        $this->assertSame('2026-05-20', $this->loan->fresh()->next_deduction_date instanceof \DateTimeInterface ? $this->loan->fresh()->next_deduction_date->format('Y-m-d') : substr($this->loan->fresh()->next_deduction_date, 0, 10));
        $this->assertNotNull(Payroll::where('payroll_run_id', $main->id)->value('published_at'));

        // Start page lists the locked run and offers a new separate run
        $this->get('http://localhost/payroll/runs/create?month=2026-04')->assertOk()
            ->assertSee('Pay runs in April 2026')->assertSee('new, separate')->assertSee('already in an approved April run');

        // A second, bonus run for just Mary in the same month — the loan isn't deducted twice
        $this->post('http://localhost/payroll/runs', ['period' => '2026-04', 'coverage' => 'people', 'employees' => [$this->mary->id], 'run_type' => 'bonus'])->assertRedirect();
        $bonus = PayrollRun::where('period', '2026-04')->where('id', '!=', $main->id)->firstOrFail();
        $this->assertSame(1, $bonus->payrolls()->count());
        $this->assertSame('bonus', $bonus->run_type);
        $this->assertSame('April 2026 · Bonus / incentive #2', $bonus->alias);
        $this->assertSame(0, $bonus->payrolls()->first()->items()->where('description', 'like', 'Loan Repayment%')->count());
        $this->assertSame(2, PayrollRun::where('period', '2026-04')->count());

        // Picking nobody is refused
        $this->post('http://localhost/payroll/runs', ['period' => '2026-04', 'coverage' => 'people', 'employees' => []])->assertSessionHasErrors('employees');

        // Recalculate the bonus run for Mary's department instead
        $dept = $this->mary->department;
        $this->post('http://localhost/payroll/runs', ['period' => '2026-04', 'run_id' => $bonus->id, 'coverage' => 'departments', 'departments' => [$dept], 'run_type' => 'bonus'])->assertRedirect();
        $this->assertSame(Employee::where('employment_status', 'Active')->where('department', $dept)->count(), $bonus->fresh()->payrolls()->count());
        $this->assertSame(2, PayrollRun::where('period', '2026-04')->count(), 'recalculating does not add a run');
        $this->assertSame('Finance', $bonus->fresh()->coverageSummary());

        // "No department" picks people without one
        $this->post('http://localhost/payroll/runs', ['period' => '2026-04', 'run_id' => $bonus->id, 'coverage' => 'departments', 'departments' => ['__none__']])->assertRedirect();
        $this->assertSame(Employee::where('employment_status', 'Active')->whereNull('department')->count(), $bonus->fresh()->payrolls()->count());
        $this->assertFalse($bonus->fresh()->payrolls()->where('employee_id', $this->mary->id)->exists());

        // Home groups the month with its status counts
        $this->get('http://localhost/payroll/runs/all')->assertOk()->assertSee('April 2026')->assertSee('2 runs')->assertSee('1 locked')->assertSee('1 being prepared')->assertSee('Bonus / incentive')
            ->assertSee('How payroll works');
        $this->get('http://localhost/payroll/runs/all?status=locked')->assertOk()->assertSee('April 2026 payroll')->assertDontSee('Bonus / incentive #2');

        // Unlock: reason required
        $this->get('http://localhost/payroll/runs/' . $main->id)->assertOk()->assertSee('Unlock to edit');
        $this->post('http://localhost/payroll/runs/' . $main->id . '/unlock', ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post('http://localhost/payroll/runs/' . $main->id . '/unlock', ['reason' => 'Housing allowance was missed'])->assertRedirect()->assertSessionHas('success');
        $main->refresh();
        $this->assertTrue($main->isEditable());
        $this->assertNull($main->finalized_at);
        $this->assertSame(1, $main->unlock_count);
        $this->assertSame('Housing allowance was missed', $main->events()->where('action', 'unlocked')->value('comment'));
        $this->assertSame(0, LoanPayment::where('loan_id', $this->loan->id)->count(), 'repayment reversed');
        $loan = $this->loan->fresh();
        $this->assertEquals(1200, (float) $loan->balance);
        $this->assertSame('2026-04-20', substr((string) $loan->next_deduction_date, 0, 10));
        $this->assertNull(Payroll::where('payroll_run_id', $main->id)->whereNotNull('published_at')->value('id'), 'hidden from the portal');
        $this->assertSame('Draft', Payroll::where('payroll_run_id', $main->id)->value('status'));
        $this->get('http://localhost/payroll/runs/' . $main->id)->assertOk()->assertSee('Unlocked by')->assertSee('Housing allowance was missed');

        // Can't unlock something that isn't locked; re-lock posts the repayment again exactly once
        $this->post('http://localhost/payroll/runs/' . $main->id . '/unlock', ['reason' => 'Again please'])->assertSessionHas('error');
        $this->lock($main->fresh());
        $this->assertSame(1, LoanPayment::where('loan_id', $this->loan->id)->count());
        $this->assertEquals(800, (float) $this->loan->fresh()->balance);
    }

    public function test_only_approvers_can_unlock(): void
    {
        $this->actingAs($this->admin);
        $this->post('http://localhost/payroll/runs', ['period' => '2026-04', 'coverage' => 'all'])->assertRedirect();
        $run = PayrollRun::where('period', '2026-04')->firstOrFail();
        $this->lock($run->fresh());

        $clerk = User::firstOrCreate(['email' => 'clerk.unlock@pikozm.com'], ['company_id' => $this->admin->company_id, 'name' => 'Clerk', 'password' => bcrypt('x12345678'), 'role' => 'employee', 'email_verified_at' => now()]);
        try {
            $this->actingAs($clerk)->post('http://localhost/payroll/runs/' . $run->id . '/unlock', ['reason' => 'Let me in please'])->assertForbidden();
            $this->assertTrue($run->fresh()->isLocked());
        } finally {
            $clerk->delete();
        }
    }
}
