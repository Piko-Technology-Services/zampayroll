<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\PayrollRun;
use App\Models\PayrollRunAdjustment;
use App\Models\User;
use App\Services\HrActions;
use App\Services\Overtime\OvertimeService;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Overtime: breaks and overnight shifts, company rates, approval with corrected hours,
 * and payroll that pays each approved claim exactly once.
 */
class OvertimeWorkflowTest extends TestCase
{
    private User $admin;
    private Employee $mary;
    private User $maryUser;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Tenancy::reset();
        DB::setDefaultConnection('mysql');
        $this->admin = User::where('email', 'admin@pikozm.com')->first();
        Tenancy::setCompanyId($this->admin->company_id);
        $this->mary = Employee::where('employee_id', 'EMP-001')->first();
        $this->mary->forceFill(['salary' => 8000, 'net_salary' => 6500])->save();
        $this->maryUser = User::firstOrCreate(['email' => 'mary.ot@pikozm.com'], ['company_id' => $this->admin->company_id, 'name' => 'Mary Banda',
            'password' => bcrypt('x12345678'), 'role' => 'employee', 'employee_id' => $this->mary->id, 'email_verified_at' => now()]);
        Company::whereKey($this->admin->company_id)->update(['overtime_settings' => null]);
        OvertimeRequest::where('employee_id', $this->mary->id)->delete();
    }

    public function test_portal_claim_with_break_and_overnight_shift(): void
    {
        $service = app(OvertimeService::class);
        $date = today()->subDays(3)->toDateString();

        // Reason is required by default.
        $this->actingAs($this->maryUser)->post('http://localhost/portal/overtime', ['date' => $date, 'start_time' => '22:00', 'end_time' => '02:00', 'break_minutes' => 30, 'type' => 'normal'])
            ->assertSessionHasErrors('comment');

        $this->actingAs($this->maryUser)->post('http://localhost/portal/overtime', ['date' => $date, 'start_time' => '22:00', 'end_time' => '02:00', 'break_minutes' => 30, 'type' => 'normal', 'comment' => 'Stock count'])
            ->assertSessionHas('success');
        $ot = OvertimeRequest::where('employee_id', $this->mary->id)->latest('id')->first();
        $this->assertEquals(3.5, (float) $ot->hours);
        $hourly = round(8000 / $this->mary->working_days_per_month / 8, 4);
        $this->assertEquals(round($hourly * 1.5 * 3.5, 2), (float) $ot->amount);
        $this->assertSame('basic', $ot->rate_base);

        // Overlapping claim (01:00 next morning is inside the overnight shift) is stopped.
        $next = today()->subDays(2)->toDateString();
        $this->actingAs($this->maryUser)->post('http://localhost/portal/overtime', ['date' => $next, 'start_time' => '01:00', 'end_time' => '03:00', 'type' => 'normal', 'comment' => 'x'])
            ->assertSessionHasErrors('start_time');

        // Company changes its rates: new claims use them, the old one keeps its amount.
        $this->actingAs($this->admin)->post('http://localhost/overtime/settings', [
            'rates' => [['key' => 'normal', 'label' => 'Normal overtime', 'hint' => 'Working day', 'multiplier' => 1.75], ['key' => '', 'label' => 'Night shift', 'hint' => '', 'multiplier' => 2.25]],
            'base' => 'basic', 'hours_per_day' => 8, 'max_hours_per_day' => 12, 'days_back' => 60, 'require_reason' => 1,
        ])->assertSessionHas('success');
        $this->assertSame(['normal', 'night_shift'], array_keys($service->types()));
        $amountBefore = (float) $ot->amount;
        $this->assertEquals($amountBefore, (float) $ot->fresh()->amount);

        // Employee cancels: kept as cancelled.
        $this->actingAs($this->maryUser)->delete("http://localhost/portal/overtime/{$ot->id}")->assertSessionHas('success');
        $this->assertSame('cancelled', $ot->fresh()->status);
    }

    public function test_approval_with_fewer_hours_and_payroll_pays_once(): void
    {
        $hr = app(HrActions::class);
        // Two claims in July, entered by HR.
        $this->actingAs($this->admin)->post('http://localhost/overtime/dashboard/record', ['employee_id' => $this->mary->id, 'date' => '2026-07-14', 'start_time' => '17:00', 'end_time' => '21:00', 'type' => 'normal', 'comment' => 'Audit prep'])
            ->assertSessionHas('success');
        $a = OvertimeRequest::where('employee_id', $this->mary->id)->latest('id')->first();
        $this->assertSame('pending', $a->status);

        // Approve only 3 of the 4 hours: amount follows.
        $full = (float) $a->amount;
        $this->actingAs($this->admin)->post("http://localhost/overtime/dashboard/requests/{$a->id}/approve", ['hours' => 3])->assertSessionHas('success');
        $a->refresh();
        $this->assertSame('approved', $a->status);
        $this->assertEquals(3, (float) $a->hours);
        $this->assertEquals(4, (float) $a->requested_hours);
        $this->assertEqualsWithDelta($full * 0.75, (float) $a->amount, 0.02);
        $this->assertTrue(AuditLog::where('action', 'overtime.approved')->where('subject_id', $a->id)->exists());
        // Approving again is refused.
        $this->actingAs($this->admin)->post("http://localhost/overtime/dashboard/requests/{$a->id}/approve")->assertSessionHasErrors('overtime');

        // July payroll picks it up once.
        $july = $hr->preparePayroll('2026-07', null, $this->admin);
        $this->assertSame(1, PayrollRunAdjustment::where('payroll_run_id', $july->id)->where('overtime_request_id', $a->id)->count());
        $this->assertSame($july->id, $a->fresh()->payroll_run_id);
        $hr->generatePayroll($july, $this->admin); // recalculating doesn't duplicate
        $this->assertSame(1, PayrollRunAdjustment::where('overtime_request_id', $a->id)->count());

        // A second July run (e.g. a supplementary run) does not pay it again.
        $extra = PayrollRun::create(['period' => '2026-07', 'alias' => 'July extra', 'status' => PayrollRun::DRAFT, 'include_overtime' => true]);
        $hr->generatePayroll($extra, $this->admin);
        $this->assertSame(0, PayrollRunAdjustment::where('payroll_run_id', $extra->id)->whereNotNull('overtime_request_id')->count());
        $extra->delete();

        // July is approved (locked). A late July claim approved now goes into August.
        $july->forceFill(['status' => PayrollRun::APPROVED])->save();
        $late = app(OvertimeService::class)->apply($this->mary, ['date' => '2026-07-28', 'start_time' => '18:00', 'end_time' => '20:00', 'type' => 'normal', 'comment' => 'Late'], $this->admin, false);
        app(OvertimeService::class)->approve($late, $this->admin);
        $aug = $hr->preparePayroll('2026-08', null, $this->admin);
        $lines = PayrollRunAdjustment::where('payroll_run_id', $aug->id)->whereNotNull('overtime_request_id')->get();
        $this->assertSame([$late->id], $lines->pluck('overtime_request_id')->all());
        $this->assertStringContainsString('from July', $lines->first()->name);

        // Overtime paid in a locked payroll can't be cancelled; one in a payroll still being prepared can, and comes out of it.
        $this->actingAs($this->admin)->post("http://localhost/overtime/dashboard/requests/{$a->id}/cancel")->assertSessionHasErrors('overtime');
        $this->actingAs($this->admin)->post("http://localhost/overtime/dashboard/requests/{$late->id}/cancel", ['reason' => 'Entered twice'])->assertSessionHas('success');
        $this->assertSame(0, PayrollRunAdjustment::where('overtime_request_id', $late->id)->count());

        // Report and CSV.
        $this->actingAs($this->admin)->get('http://localhost/overtime/report?month=2026-07')->assertOk()->assertSee('Mary Banda');
        $csv = $this->actingAs($this->admin)->get('http://localhost/overtime/report?month=2026-07&format=csv');
        $csv->assertOk();
        $this->assertStringContainsString('Mary Banda', $csv->streamedContent());
        $this->actingAs($this->admin)->get('http://localhost/overtime/dashboard/requests?status=all')->assertOk()->assertSee('In July 2026 payroll');
    }

    protected function tearDown(): void
    {
        Tenancy::setCompanyId($this->admin->company_id);
        foreach (PayrollRun::withTrashed()->whereIn('period', ['2026-07', '2026-08'])->get() as $run) {
            PayrollRunAdjustment::where('payroll_run_id', $run->id)->delete();
            foreach ($run->payrolls()->get() as $p) {
                $p->items()->delete();
                $p->delete();
            }
            DB::table('payroll_run_events')->where('payroll_run_id', $run->id)->delete();
            $run->forceDelete();
        }
        OvertimeRequest::where('employee_id', $this->mary->id)->delete();
        Company::whereKey($this->admin->company_id)->update(['overtime_settings' => null]);
        User::where('email', 'mary.ot@pikozm.com')->delete();
        Tenancy::reset();
        parent::tearDown();
    }
}
