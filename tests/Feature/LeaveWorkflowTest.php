<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRecord;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Leave: types, balance checks, manager → HR approval, cancellation that gives everything back.
 */
class LeaveWorkflowTest extends TestCase
{
    private User $admin;
    private Employee $mary;
    private Employee $john;
    private User $maryUser;
    private User $manager;

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
        $this->john = Employee::where('employee_id', 'EMP-002')->first();
        $this->mary->forceFill(['department' => 'Sales', 'leave_days_balance' => 10, 'leave_days_entitled' => 24])->save();
        $this->john->forceFill(['department' => 'Finance', 'leave_days_balance' => 10])->save();

        $this->maryUser = User::firstOrCreate(['email' => 'mary.portal@pikozm.com'], ['company_id' => $cid, 'name' => 'Mary Banda', 'password' => bcrypt('x12345678'),
            'role' => 'employee', 'employee_id' => $this->mary->id, 'email_verified_at' => now()]);
        // A manager who is himself an employee in Sales.
        $this->manager = User::firstOrCreate(['email' => 'mgr@pikozm.com'], ['company_id' => $cid, 'name' => 'Sam Sales', 'password' => bcrypt('x12345678'),
            'role' => 'manager', 'employee_id' => $this->john->id, 'email_verified_at' => now()]);
        $this->john->forceFill(['department' => 'Sales'])->save(); // Sam (linked to John's record) manages Sales
        Company::whereKey($cid)->update(['leave_hr_confirm' => false, 'leave_allow_negative' => false]);
        LeaveRequest::whereIn('employee_id', [$this->mary->id, $this->john->id])->delete();
        Attendance::whereIn('employee_id', [$this->mary->id])->delete();
        LeaveRecord::whereIn('employee_id', [$this->mary->id])->delete();
    }

    private function nextMonday(int $weeks = 1)
    {
        return now()->startOfWeek()->addWeeks($weeks)->startOfDay();
    }

    public function test_types_are_created_and_rules_are_checked_when_applying(): void
    {
        $this->actingAs($this->maryUser)->get('http://localhost/portal/leave')->assertOk()->assertSee('Annual leave')->assertSee('Unpaid leave');
        $this->assertSame(9, LeaveType::count());

        $mon = $this->nextMonday(2);
        // More days than the balance (10): three working weeks = 15 days.
        $this->actingAs($this->maryUser)->post('http://localhost/portal/leave', ['leave_type' => 'annual', 'start_date' => $mon->toDateString(), 'end_date' => $mon->copy()->addDays(18)->toDateString()])
            ->assertSessionHasErrors('end_date');

        // A week is fine (5 days).
        $this->actingAs($this->maryUser)->post('http://localhost/portal/leave', ['leave_type' => 'annual', 'start_date' => $mon->toDateString(), 'end_date' => $mon->copy()->addDays(4)->toDateString()])
            ->assertRedirect()->assertSessionHas('success');
        $req = LeaveRequest::where('employee_id', $this->mary->id)->latest('id')->first();
        $this->assertSame('pending', $req->status);
        $this->assertEquals(5, (float) $req->days);

        // Overlap is stopped; and pending days count against what's left (10 - 5 = 5 available).
        $this->actingAs($this->maryUser)->post('http://localhost/portal/leave', ['leave_type' => 'annual', 'start_date' => $mon->copy()->addDays(2)->toDateString(), 'end_date' => $mon->copy()->addDays(3)->toDateString()])
            ->assertSessionHasErrors('start_date');
        $next = $mon->copy()->addWeeks(1);
        $this->actingAs($this->maryUser)->post('http://localhost/portal/leave', ['leave_type' => 'annual', 'start_date' => $next->toDateString(), 'end_date' => $next->copy()->addDays(7)->toDateString()])
            ->assertSessionHasErrors('end_date'); // 6 days > 5 available

        // Sick leave needs a document by default.
        $this->actingAs($this->maryUser)->post('http://localhost/portal/leave', ['leave_type' => 'sick', 'start_date' => $next->toDateString(), 'end_date' => $next->toDateString()])
            ->assertSessionHasErrors('documents');

        // Employee cancels a pending request: kept as "cancelled", not deleted.
        $r = $this->actingAs($this->maryUser)->delete("http://localhost/portal/leave/{$req->id}");
        $r->assertSessionHas('success');
        $this->assertSame('cancelled', $req->fresh()->status);
        $this->assertTrue($req->fresh()->cancelled_by_employee);
    }

    public function test_manager_approves_then_hr_cancels_and_everything_is_given_back(): void
    {
        // Leave across a month end: last Thursday of a month to the following Tuesday.
        $start = now()->addMonthNoOverflow()->endOfMonth()->startOfDay();
        while (! $start->isThursday()) {
            $start->subDay();
        }
        $end = $start->copy()->addDays(5); // Thu..Tue = 4 working days
        LeaveType::where('key', 'annual')->update(['requires_document' => false]);

        $this->actingAs($this->maryUser)->post('http://localhost/portal/leave', ['leave_type' => 'annual', 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString()])->assertSessionHas('success');
        $req = LeaveRequest::where('employee_id', $this->mary->id)->latest('id')->first();
        $days = (float) $req->days;
        $this->assertGreaterThanOrEqual(3, $days);

        // Manager of Sales sees and approves it.
        $this->actingAs($this->manager)->get('http://localhost/leave/dashboard/requests')->assertOk()->assertSee('Mary Banda');
        $this->actingAs($this->manager)->post("http://localhost/leave/dashboard/requests/{$req->id}/approve", ['comment' => 'Enjoy'])->assertSessionHas('success');
        $req->refresh();
        $this->assertSame('approved', $req->status);
        $this->assertEquals(10 - $days, (float) $this->mary->fresh()->leave_days_balance);
        $this->assertEquals($days, (float) $req->deducted_days);

        // Leave sheet split across the two months; attendance marked.
        $sheet = LeaveRecord::where('employee_id', $this->mary->id)->orderBy('year')->orderBy('month')->get();
        $this->assertCount(2, $sheet);
        $this->assertEquals($days, (float) $sheet->sum('days_taken'));
        $this->assertSame((int) $days, Attendance::where('employee_id', $this->mary->id)->where('status', 'leave')->count());

        // Approving twice does nothing more.
        $this->actingAs($this->admin)->post("http://localhost/leave/dashboard/requests/{$req->id}/approve")->assertSessionHasErrors('leave');
        $this->assertEquals(10 - $days, (float) $this->mary->fresh()->leave_days_balance);

        // The manager can't cancel approved leave (HR oversight), HR can.
        $this->actingAs($this->manager)->post("http://localhost/leave/dashboard/requests/{$req->id}/cancel")->assertSessionHasErrors('leave');
        $this->actingAs($this->admin)->post("http://localhost/leave/dashboard/requests/{$req->id}/cancel", ['reason' => 'Changed plans'])->assertSessionHas('success');
        $this->assertSame('cancelled', $req->fresh()->status);
        $this->assertEquals(10, (float) $this->mary->fresh()->leave_days_balance);
        $this->assertEquals(0, (float) LeaveRecord::where('employee_id', $this->mary->id)->sum('days_taken'));
        $this->assertSame(0, Attendance::where('employee_id', $this->mary->id)->count());

        $this->assertTrue(AuditLog::where('action', 'leave.cancelled')->where('subject_id', $req->id)->exists());
        $this->assertTrue(AuditLog::where('action', 'leave.approved')->where('subject_id', $req->id)->exists());
    }

    public function test_hr_confirms_manager_approvals_when_switched_on_and_sick_leave_keeps_balance(): void
    {
        $this->actingAs($this->admin)->post('http://localhost/leave/types/policy', ['leave_hr_confirm' => 1])->assertSessionHas('success');
        LeaveType::where('key', 'sick')->update(['requires_document' => true, 'document_after_days' => 2]);

        $mon = $this->nextMonday(3);
        $this->actingAs($this->maryUser)->post('http://localhost/portal/leave', ['leave_type' => 'sick', 'start_date' => $mon->toDateString(), 'end_date' => $mon->copy()->addDay()->toDateString()])->assertSessionHas('success');
        $req = LeaveRequest::where('employee_id', $this->mary->id)->latest('id')->first();

        $this->actingAs($this->manager)->post("http://localhost/leave/dashboard/requests/{$req->id}/approve")->assertSessionHas('success');
        $this->assertSame('manager_approved', $req->fresh()->status);
        $this->assertEquals(10, (float) $this->mary->fresh()->leave_days_balance);
        // Manager can't do the HR step.
        $this->actingAs($this->manager)->post("http://localhost/leave/dashboard/requests/{$req->id}/approve")->assertSessionHasErrors('leave');

        $this->actingAs($this->admin)->post("http://localhost/leave/dashboard/requests/{$req->id}/approve")->assertSessionHas('success');
        $req->refresh();
        $this->assertSame('approved', $req->status);
        $this->assertEquals(10, (float) $this->mary->fresh()->leave_days_balance);           // sick leave doesn't use annual balance
        $this->assertSame(2, Attendance::where('employee_id', $this->mary->id)->where('status', 'sick')->count());
        $this->assertEquals(0, (float) LeaveRecord::where('employee_id', $this->mary->id)->sum('days_taken'));
    }

    public function test_hr_records_leave_and_managers_only_see_their_team(): void
    {
        $mon = $this->nextMonday(4);
        $this->actingAs($this->admin)->post('http://localhost/leave/dashboard/record', [
            'employee_id' => $this->mary->id, 'leave_type' => 'annual', 'start_date' => $mon->toDateString(), 'end_date' => $mon->copy()->addDays(1)->toDateString(), 'approve_now' => 1,
        ])->assertSessionHas('success');
        $req = LeaveRequest::where('employee_id', $this->mary->id)->latest('id')->first();
        $this->assertSame('approved', $req->status);
        $this->assertSame($this->admin->id, $req->recorded_by);
        $this->assertEquals(8, (float) $this->mary->fresh()->leave_days_balance);

        // A manager in another department doesn't see Mary.
        $this->john->forceFill(['department' => 'Finance'])->save();
        $this->manager = $this->manager->fresh();
        $this->flushSession(); // the "Leave recorded for Mary Banda" message would show otherwise
        $this->actingAs($this->manager)->get('http://localhost/leave/dashboard/requests?status=all')->assertOk()->assertDontSee('Mary Banda');

        // Leave types page is HR-only; a manager gets 403.
        $this->actingAs($this->manager)->get('http://localhost/leave/types')->assertForbidden();
        $this->actingAs($this->admin)->get('http://localhost/leave/types')->assertOk()->assertSee('How approval works');

        // The other company can't touch it.
        $other = User::where('email', 'admin@second.com')->first();
        Tenancy::reset();
        $this->actingAs($other)->post("http://localhost/leave/dashboard/requests/{$req->id}/cancel")->assertNotFound();
        Tenancy::setCompanyId($this->admin->company_id);
        $this->assertSame('approved', $req->fresh()->status);
    }

    protected function tearDown(): void
    {
        Tenancy::setCompanyId($this->admin->company_id);
        Company::whereKey($this->admin->company_id)->update(['leave_hr_confirm' => false]);
        LeaveRequest::whereIn('employee_id', [$this->mary->id, $this->john->id])->delete();
        Attendance::whereIn('employee_id', [$this->mary->id])->delete();
        LeaveRecord::whereIn('employee_id', [$this->mary->id])->delete();
        $this->mary->forceFill(['leave_days_balance' => 10, 'department' => null])->save();
        $this->john->forceFill(['leave_days_balance' => 10, 'department' => null])->save();
        User::whereIn('email', ['mary.portal@pikozm.com', 'mgr@pikozm.com'])->delete();
        Tenancy::reset();
        parent::tearDown();
    }
}
