<?php

namespace Tests\Feature;

use App\Jobs\SendPayslipEmail;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\HrActions;
use App\Services\Payslips\PayslipDelivery;
use App\Services\Payslips\Payslips;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** One confidential payslip template everywhere, and sharing by portal / work email / personal email. */
class PayslipsTest extends TestCase
{
    private User $admin;
    private Employee $mary;
    private User $maryUser;
    private PayrollRun $run;
    private array $maryWas = [];

    protected function setUp(): void
    {
        parent::setUp();
        Tenancy::reset();
        DB::setDefaultConnection('mysql');
        $this->admin = User::where('email', 'admin@pikozm.com')->first();
        Tenancy::setCompanyId($this->admin->company_id);
        $this->mary = Employee::where('employee_id', 'EMP-001')->first();
        $this->maryWas = $this->mary->only(['net_salary', 'company_email', 'personal_email', 'bank_account_no', 'bank_name']);
        $this->mary->forceFill(['net_salary' => 6500, 'company_email' => 'mary.work@pikozm.com', 'personal_email' => 'mary.home@example.com', 'bank_account_no' => '0011223344', 'bank_name' => 'Zanaco'])->save();
        $this->maryUser = User::firstOrCreate(['email' => 'mary.slip@pikozm.com'], ['company_id' => $this->admin->company_id, 'name' => 'Mary Banda',
            'password' => bcrypt('x12345678'), 'role' => 'employee', 'employee_id' => $this->mary->id, 'email_verified_at' => now()]);

        PayrollRun::withTrashed()->where('period', '2026-03')->get()->each(fn ($r) => $r->forceDelete());
        $this->run = PayrollRun::create(['period' => '2026-03', 'alias' => 'March 2026 payroll', 'created_by' => $this->admin->id, 'status' => PayrollRun::DRAFT]);
        app(HrActions::class)->generatePayroll($this->run, $this->admin);
    }

    protected function tearDown(): void
    {
        $this->run->payrolls()->each(fn ($p) => $p->items()->delete());
        $this->run->payrolls()->delete();
        $this->run->forceDelete();
        $this->maryUser->delete();
        $this->mary->forceFill($this->maryWas)->save();
        parent::tearDown();
    }

    private function maryPayroll(): Payroll
    {
        return Payroll::where('payroll_run_id', $this->run->id)->where('employee_id', $this->mary->id)->firstOrFail();
    }

    public function test_same_confidential_template_for_screen_pdf_and_portal(): void
    {
        $p = $this->maryPayroll();
        $data = app(Payslips::class)->data($p);
        $this->assertFalse($data['logo']['own'], 'no company logo uploaded → ZamPayroll logo');
        $this->assertStringStartsWith('data:image/png;base64,', $data['logo']['src']);
        $this->assertSame('•••• 3344', app(Payslips::class)->mask('0011223344'));
        $this->assertEquals(6500, $data['net']);
        $this->assertGreaterThan(0, $data['paye'] + $data['napsa'] + $data['nhima']);

        // Admin: all payslips of a draft run say DRAFT; single view and PDF use the same template
        $this->actingAs($this->admin)->get('http://localhost/payroll/runs/' . $this->run->id . '/payslips')->assertOk()
            ->assertSee('STRICTLY PRIVATE &amp; CONFIDENTIAL', false)->assertSee('DRAFT PAYSLIP')->assertSee('Mary Banda')->assertSee('•••• 3344');
        $this->get('http://localhost/payroll/payroll/' . $p->id . '/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('http://localhost/payroll/runs/' . $this->run->id . '/payslips/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');

        // Not visible on the portal until approved
        $this->actingAs($this->maryUser)->get('http://localhost/portal/payslips/' . $p->id)->assertNotFound();

        // Approved (auto-publish is on by default) → visible, no DRAFT
        $this->run->update(['status' => PayrollRun::APPROVED]);
        Payroll::where('payroll_run_id', $this->run->id)->update(['status' => 'Approved']);
        app(PayslipDelivery::class)->onApproved($this->run);
        $this->get('http://localhost/portal/payslips/' . $p->id)->assertOk()->assertSee('PRIVATE &amp; CONFIDENTIAL', false)->assertDontSee('DRAFT PAYSLIP');
        $this->get('http://localhost/portal/payslips/' . $p->id . '/download')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_share_by_portal_work_and_personal_email(): void
    {
        Queue::fake();
        $p = $this->maryPayroll();

        // Draft runs can't be shared
        $this->actingAs($this->admin)->post('http://localhost/payroll/runs/' . $this->run->id . '/payslips/share', ['payrolls' => [$p->id], 'work' => 1])->assertSessionHas('error');

        $this->run->update(['status' => PayrollRun::APPROVED]);
        Payroll::where('payroll_run_id', $this->run->id)->update(['status' => 'Approved', 'published_at' => null]);
        $this->get('http://localhost/payroll/runs/' . $this->run->id . '/payslips/share')->assertOk()->assertSee('Self-service portal')->assertSee('mary.home@example.com');

        // Nothing chosen → asked to choose
        $this->post('http://localhost/payroll/runs/' . $this->run->id . '/payslips/share', ['payrolls' => [$p->id]])->assertSessionHasErrors('portal');

        // Both emails, no portal, remembered as default
        $this->post('http://localhost/payroll/runs/' . $this->run->id . '/payslips/share', ['payrolls' => [$p->id], 'work' => 1, 'personal' => 1, 'remember' => 1])->assertSessionHas('success');
        Queue::assertPushed(SendPayslipEmail::class, 1);
        $p->refresh();
        $this->assertSame('mary.work@pikozm.com, mary.home@example.com', $p->emailed_to);
        $this->assertNull($p->published_at);
        $this->assertSame(['portal' => false, 'work' => true, 'personal' => true, 'auto_portal' => true], app(PayslipDelivery::class)->settings(Company::find($this->admin->company_id)));

        // Portal only → visible to Mary, nothing emailed
        $this->post('http://localhost/payroll/runs/' . $this->run->id . '/payslips/share', ['payrolls' => [$p->id], 'portal' => 1])->assertSessionHas('success');
        Queue::assertPushed(SendPayslipEmail::class, 1);
        $this->assertNotNull($p->fresh()->published_at);
        $this->actingAs($this->maryUser)->get('http://localhost/portal/payslips/' . $p->id)->assertOk();

        // Remove from portal again
        $this->actingAs($this->admin)->post('http://localhost/payroll/runs/' . $this->run->id . '/payslips/share', ['payrolls' => [$p->id], 'action' => 'unpublish'])->assertSessionHas('success');
        $this->actingAs($this->maryUser)->get('http://localhost/portal/payslips/' . $p->id)->assertNotFound();

        // Missing work email → falls back to personal
        $this->assertSame(['mary.home@example.com'], app(PayslipDelivery::class)->recipients(new Employee(['personal_email' => 'mary.home@example.com']), true, false));
        app(PayslipDelivery::class)->saveSettings(Company::find($this->admin->company_id), PayslipDelivery::DEFAULTS);
    }

    public function test_amount_in_words(): void
    {
        $w = app(Payslips::class);
        $this->assertSame('Eight thousand two hundred and fifty kwacha and fifty ngwee only', $w->words(8250.50));
        $this->assertSame('One million and five kwacha only', $w->words(1000005));
        $this->assertSame('Nineteen kwacha only', $w->words(19));
    }
}
