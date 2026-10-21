<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Portal: own attendance, contact updates (audited), bank changes that HR must approve.
 */
class PortalSelfServiceTest extends TestCase
{
    private User $admin;
    private Employee $mary;
    private User $maryUser;
    private array $bankBefore;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Tenancy::reset();
        DB::setDefaultConnection('mysql');
        $this->admin = User::where('email', 'admin@pikozm.com')->first();
        Tenancy::setCompanyId($this->admin->company_id);
        $this->mary = Employee::where('employee_id', 'EMP-001')->first();
        $this->bankBefore = $this->mary->only(['bank_name', 'bank_branch', 'bank_account_no']);
        $this->mary->forceFill(['bank_name' => 'Zanaco', 'bank_account_no' => '0011223344'])->save();
        $this->maryUser = User::firstOrCreate(['email' => 'mary.self@pikozm.com'], ['company_id' => $this->admin->company_id, 'name' => 'Mary Banda',
            'password' => bcrypt('x12345678'), 'role' => 'employee', 'employee_id' => $this->mary->id, 'email_verified_at' => now()]);
        EmployeeChangeRequest::where('employee_id', $this->mary->id)->delete();
    }

    public function test_bank_change_needs_hr_approval(): void
    {
        $this->actingAs($this->maryUser)->post('http://localhost/portal/profile/bank', ['bank_name' => 'Stanbic', 'bank_account_no' => '9130001234567', 'bank_account_no_confirmation' => '9130001234568'])
            ->assertSessionHasErrors('bank_account_no_confirmation');
        $this->actingAs($this->maryUser)->post('http://localhost/portal/profile/bank', ['bank_name' => 'Stanbic', 'bank_account_no' => '9130 0012 34567', 'bank_account_no_confirmation' => '9130 0012 34567'])
            ->assertSessionHas('success');

        // Nothing changes until HR approves; the employee is emailed a security notice.
        $this->assertSame('0011223344', $this->mary->fresh()->bank_account_no);
        $change = EmployeeChangeRequest::where('employee_id', $this->mary->id)->latest('id')->first();
        $this->assertSame('pending', $change->status);
        $this->assertSame('9130001234567', $change->changes['bank_account_no']);
        $this->assertSame('0011223344', $change->previous['bank_account_no']);
        Mail::assertSent(\App\Mail\ChangeRequestMail::class, fn ($m) => $m->kind === 'requested' && $m->hasTo('mary.self@pikozm.com'));

        // Only one at a time.
        $this->actingAs($this->maryUser)->post('http://localhost/portal/profile/bank', ['bank_name' => 'FNB', 'bank_account_no' => '123456789', 'bank_account_no_confirmation' => '123456789'])
            ->assertSessionHasErrors('bank_name');

        // The employee can't approve it; another company can't see it; HR must confirm they checked.
        $this->actingAs($this->maryUser)->post("http://localhost/employees/changes/{$change->id}/approve", ['confirmed' => 1])->assertStatus(302);
        $this->assertSame('pending', $change->fresh()->status);
        $other = User::where('email', 'admin@second.com')->first();
        Tenancy::reset();
        $this->actingAs($other)->post("http://localhost/employees/changes/{$change->id}/approve", ['confirmed' => 1])->assertNotFound();
        Tenancy::setCompanyId($this->admin->company_id);

        $this->actingAs($this->admin)->get('http://localhost/employees/changes')->assertOk()->assertSee('Mary Banda');
        $this->actingAs($this->admin)->post("http://localhost/employees/changes/{$change->id}/approve")->assertSessionHasErrors('confirmed');
        $this->actingAs($this->admin)->post("http://localhost/employees/changes/{$change->id}/approve", ['confirmed' => 1, 'comment' => 'Confirmed by phone'])->assertSessionHas('success');

        $this->assertSame('9130001234567', $this->mary->fresh()->bank_account_no);
        $this->assertSame('Stanbic', $this->mary->fresh()->bank_name);
        $this->assertSame('approved', $change->fresh()->status);
        $log = AuditLog::where('action', 'employee.bank_changed')->latest('id')->first();
        $this->assertSame('0011223344', $log->changes['bank_account_no']['from']);
        $this->assertSame('9130001234567', $log->changes['bank_account_no']['to']);
    }

    public function test_attendance_page_and_audited_contact_update(): void
    {
        Attendance::where('employee_id', $this->mary->id)->delete();
        Attendance::create(['employee_id' => $this->mary->id, 'date' => now()->startOfMonth()->toDateString(), 'status' => 'present', 'time_in' => '08:00', 'time_out' => '17:00', 'hours_worked' => 9]);
        $this->actingAs($this->maryUser)->get('http://localhost/portal/attendance')->assertOk()->assertSee('My attendance')->assertSee('08:00');

        $this->actingAs($this->maryUser)->put('http://localhost/portal/profile', ['primary_phone' => '0977555444', 'next_of_kin_name' => 'Peter Banda'])->assertSessionHas('success');
        $this->assertSame('0977555444', $this->mary->fresh()->primary_phone);
        $this->assertTrue(AuditLog::where('action', 'employee.self_updated')->where('subject_id', $this->mary->id)->exists());
        Attendance::where('employee_id', $this->mary->id)->delete();
    }

    protected function tearDown(): void
    {
        Tenancy::setCompanyId($this->admin->company_id);
        EmployeeChangeRequest::where('employee_id', $this->mary->id)->delete();
        $this->mary->forceFill($this->bankBefore + ['primary_phone' => '0977000000'])->save();
        User::where('email', 'mary.self@pikozm.com')->delete();
        Tenancy::reset();
        parent::tearDown();
    }
}
