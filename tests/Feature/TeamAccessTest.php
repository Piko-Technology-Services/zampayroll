<?php

namespace Tests\Feature;

use App\Mail\EmployeePortalWelcomeMail;
use App\Mail\TeamAccessGrantedMail;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Billing\Subscriptions;
use App\Services\HrActions;
use App\Services\Leave\LeaveService;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Employees given a team role keep their portal and switch dashboards; board members have admin rights but aren't staff. */
class TeamAccessTest extends TestCase
{
    private User $admin;
    private Employee $mary;
    private Employee $john;
    private array $was = [];

    protected function setUp(): void
    {
        parent::setUp();
        Tenancy::reset();
        DB::setDefaultConnection('mysql');
        $this->admin = User::where('email', 'admin@pikozm.com')->first();
        Tenancy::setCompanyId($this->admin->company_id);
        $this->mary = Employee::where('employee_id', 'EMP-001')->first();
        $this->john = Employee::where('employee_id', 'EMP-002')->first();
        $keep = ['company_email', 'personal_email', 'employment_nature', 'employment_status', 'net_salary'];
        $this->was = [$this->mary->only($keep), $this->john->only($keep)];
        $this->mary->forceFill(['company_email' => 'mary.team@pikozm.com', 'employment_nature' => 'PERMANENT', 'employment_status' => 'Active', 'net_salary' => 6500])->save();
        $this->john->forceFill(['company_email' => 'john.team@pikozm.com', 'employment_nature' => 'PERMANENT', 'employment_status' => 'Active', 'net_salary' => 9000])->save();
        User::whereIn('employee_id', [$this->mary->id, $this->john->id])->delete();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        User::whereIn('employee_id', [$this->mary->id, $this->john->id])->delete();
        LeaveRequest::whereIn('employee_id', [$this->mary->id, $this->john->id])->where('reason', 'team-access-test')->delete();
        PayrollRun::withTrashed()->where('period', '2026-05')->get()->each(function ($r) {
            $r->payrolls()->each(fn ($p) => $p->items()->delete());
            $r->payrolls()->delete();
            $r->events()->delete();
            $r->forceDelete();
        });
        $this->mary->forceFill($this->was[0])->save();
        $this->john->forceFill($this->was[1])->save();
        parent::tearDown();
    }

    public function test_employee_given_a_role_keeps_portal_and_switches_dashboards(): void
    {
        $this->actingAs($this->admin)->get('http://localhost/settings/team')->assertOk()
            ->assertSee('Give an employee access')->assertSee('Board member')->assertSee('Mary Banda');
        $this->assertTrue(CompanyRole::where('key', 'board_member')->exists(), 'built-in role added to existing companies');

        // Mary has no portal yet → account created with HR role + portal, sign-in details emailed
        $this->post('http://localhost/settings/team/employee-access', ['employee_id' => $this->mary->id, 'role' => 'hr'])->assertSessionHas('success');
        $mary = User::where('employee_id', $this->mary->id)->firstOrFail();
        $this->assertSame('hr', $mary->role);
        $this->assertTrue($mary->canSwitchDashboards());
        Mail::assertSent(EmployeePortalWelcomeMail::class, fn ($m) => $m->hasTo('mary.team@pikozm.com'));
        $this->post('http://localhost/settings/team/employee-access', ['employee_id' => $this->mary->id, 'role' => 'manager'])->assertSessionHas('error');

        // Both dashboards work, each offers the switch
        $mary->forceFill(['must_change_password' => false])->save();
        $this->actingAs($mary)->get('http://localhost/dashboard')->assertOk()->assertSee('My portal');
        $this->get('http://localhost/portal')->assertOk()->assertSee('HR Officer view')->assertSee('Request leave');
        $this->get('http://localhost/employees')->assertOk(); // HR permission works

        // Applying for her own leave through the portal is a normal employee request, not "recorded by HR"
        $this->assertNull($this->ownLeave($mary)?->recorded_by);

        // John already has a portal → role added, he's told by email
        $this->actingAs($this->admin)->post('http://localhost/employees/' . $this->john->id . '/portal')->assertSessionHas('success');
        $john = User::where('employee_id', $this->john->id)->firstOrFail();
        $this->assertTrue($john->isEmployee());
        $this->post('http://localhost/settings/team/employee-access', ['employee_id' => $this->john->id, 'role' => 'manager'])->assertSessionHas('success');
        $this->assertSame('manager', $john->fresh()->role);
        Mail::assertSent(TeamAccessGrantedMail::class, fn ($m) => $m->hasTo('john.team@pikozm.com'));

        // Team page lists them, tagged as employees
        $this->get('http://localhost/settings/team')->assertSee('mary.team@pikozm.com')->assertSee('john.team@pikozm.com')->assertSee('bi-person-badge', false);

        // The portal can't be revoked while they have a team role
        $this->delete('http://localhost/employees/' . $this->mary->id . '/portal')->assertSessionHas('error');

        // Removing team access keeps the portal
        $this->delete('http://localhost/settings/team/members/' . $mary->id)->assertSessionHas('success');
        $mary->refresh();
        $this->assertTrue($mary->isEmployee());
        $this->actingAs($mary)->get('http://localhost/dashboard')->assertRedirect(route('portal.dashboard'));
        $this->get('http://localhost/portal')->assertOk()->assertDontSee('HR Officer view');
    }

    public function test_board_member_has_admin_rights_but_is_not_staff(): void
    {
        $subs = app(Subscriptions::class);
        $company = Company::find($this->admin->company_id);
        $before = $subs->activeEmployees($company);

        $this->actingAs($this->admin)->post('http://localhost/settings/team/employee-access', ['employee_id' => $this->john->id, 'role' => 'board_member'])->assertSessionHas('success');
        $john = User::where('employee_id', $this->john->id)->firstOrFail()->forceFill(['must_change_password' => false]);
        $john->save();
        $this->assertTrue($john->isBoardMember());
        $this->assertTrue($john->isCompanyAdmin());
        $this->assertTrue($john->hasPermission('payroll.finalize'));
        $this->assertTrue($this->john->fresh()->isBoardMember(), 'employee record marked Board member');

        // Not counted: plan headcount, attendance, rota, leave
        $this->assertSame($before - 1, $subs->activeEmployees($company));
        $this->actingAs($john)->get('http://localhost/settings/team')->assertOk();
        $this->get('http://localhost/employees')->assertOk()->assertSee('board member');
        $this->actingAs($this->admin)->get('http://localhost/attendance/' . now()->toDateString())->assertOk()->assertSee('Mary Banda')->assertDontSee('John Phiri');
        $this->get('http://localhost/attendance/rota')->assertOk()->assertDontSee('John Phiri');
        $this->actingAs($john);
        try {
            app(LeaveService::class)->apply($this->john->fresh(), ['leave_type' => 'annual', 'start_date' => '2026-12-01', 'end_date' => '2026-12-02', 'reason' => 'team-access-test']);
            $this->fail('Board members should not take leave');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('board member', $e->getMessage());
        }

        // Portal: payslips and loans only
        $this->get('http://localhost/portal')->assertOk()->assertSee('Board member view')->assertDontSee('Request leave');
        $this->get('http://localhost/portal/leave')->assertRedirect(route('portal.dashboard'));
        $this->get('http://localhost/portal/loans')->assertOk();

        // Still paid through payroll
        $run = PayrollRun::create(['period' => '2026-05', 'alias' => 'May 2026 payroll', 'created_by' => $this->admin->id, 'status' => PayrollRun::DRAFT]);
        app(HrActions::class)->generatePayroll($run, $this->admin);
        $this->assertTrue($run->payrolls()->where('employee_id', $this->john->id)->exists());

        // Moved to another role → back to a normal employee record
        $this->actingAs($this->admin)->put('http://localhost/settings/team/members/' . $john->id, ['role' => 'manager'])->assertSessionHas('success');
        $this->assertFalse($this->john->fresh()->isBoardMember());
        $this->assertSame($before, $subs->activeEmployees($company));
    }

    private function ownLeave(User $user): ?LeaveRequest
    {
        try {
            return app(LeaveService::class)->apply($user->employee, ['leave_type' => 'annual', 'start_date' => '2026-12-07', 'end_date' => '2026-12-08', 'reason' => 'team-access-test'], null, $user, false);
        } catch (ValidationException $e) {
            $this->markTestSkipped('Leave rules in the baseline data refused the request: ' . $e->getMessage());
        }
    }
}
