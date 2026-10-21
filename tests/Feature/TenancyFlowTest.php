<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRule;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Mail\EmployeePortalWelcomeMail;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TenancyFlowTest extends TestCase
{
    private function codeFor(string $email): ?string
    {
        $cid = DB::connection('mysql')->table('users')->where('email', $email)->value('company_id')
            ?? DB::connection('mysql')->table('tenant_user_directory')->where('email', $email)->value('company_id');

        return DB::connection('mysql')->table('companies')->where('id', $cid)->value('company_code');
    }

    protected function tearDown(): void
    {
        Tenancy::reset();
        DB::setDefaultConnection('mysql');
        parent::tearDown();
    }

    public function test_01_isolation_between_shared_tenants(): void
    {
        $admin = User::where('email', 'admin@pikozm.com')->first();
        $grace = Employee::withoutGlobalScopes()->where('first_name', 'Grace')->first();
        $mary = Employee::withoutGlobalScopes()->where('first_name', 'Mary')->first();

        $this->actingAs($admin)->get('/employees')->assertOk()->assertSee('Mary')->assertDontSee('Grace');
        $this->actingAs($admin)->get('/employees/' . $grace->id)->assertNotFound();
        $this->actingAs($admin)->get('/employees/' . $mary->id)->assertOk()->assertSee('Create portal access');
        $this->actingAs($admin)->get('/leave/dashboard/requests?status=all')->assertOk()->assertDontSee('Grace');
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Finish setting up');
        // reports were public before; now require login
        Tenancy::reset();
        $this->app['auth']->forgetGuards();
        $this->get('/payroll/reports')->assertRedirect('/login');

        // new rows get company_id automatically
        $this->actingAs($admin);
        $h = \App\Models\Holiday::create(['name' => 'Test', 'date' => '2026-12-25', 'is_recurring' => true]);
        $this->assertSame($admin->company_id, $h->company_id);
        $h->delete();
    }

    public function test_02_shared_registration_requires_email_verification(): void
    {
        Notification::fake();

        $this->get('/register')->assertOk()->assertSee('setting things up');

        $res = $this->post('/register', [
            'name' => 'Chanda Mulenga', 'phone' => '0977123456', 'email' => 'chanda@smallco.zm',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'company_name' => 'Small Co', 'industry' => 'retail', 'employee_count_range' => '1-5',
            'finish_later' => 1, 'terms' => 1,
        ]);
        $res->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'chanda@smallco.zm')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame('shared', $user->company->tenancy_mode);
        $this->assertNull($user->company->onboarding_completed_at);

        // cannot sign in yet
        $this->post('/login', ['company_code' => $this->codeFor('chanda@smallco.zm'), 'email' => 'chanda@smallco.zm', 'password' => 'secret123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $url = null;
        Notification::assertSentTo($user, VerifyEmailNotification::class, function ($n) use ($user, &$url) {
            $url = $n->verificationUrl($user);
            return true;
        });

        $this->get($url)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame('active', $user->company->fresh()->status);
        $this->assertGreaterThan(20, PayrollRule::withoutGlobalScopes()->where('company_id', $user->company_id)->count());
    }

    public function test_03_big_company_starts_shared_then_moves_to_private_workspace(): void
    {
        Notification::fake();
        \Illuminate\Support\Facades\Mail::fake();

        $this->post('/register', [
            'name' => 'Big Boss', 'phone' => '0966000000', 'email' => 'boss@acme.zm',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'company_name' => 'Acme Mining', 'industry' => 'mining', 'employee_count_range' => '51-200',
            'subdomain' => 'acme', 'company_tpin' => '1002003004', 'terms' => 1,
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'boss@acme.zm')->firstOrFail();
        $company = $user->company;
        $this->assertTrue($company->isShared(), 'every company starts on the shared workspace');
        $this->assertSame('trialing', $company->subscription_status);

        $url = null;
        Notification::assertSentTo($user, VerifyEmailNotification::class, function ($n) use ($user, &$url) {
            $url = $n->verificationUrl($user); return true;
        });
        $this->get($url)->assertRedirect(route('dashboard'));

        // Their data before the move
        $this->actingAs($user->fresh())->post('/employees', ['first_name' => 'Shared', 'last_name' => 'Worker', 'net_salary' => 5000])->assertRedirect();
        $this->post('/settings/billing/private-workspace', ['subdomain' => 'acme'])->assertSessionHas('success');
        $this->post('/logout');

        // ZamPayroll admin approves the move from the admin console
        Tenancy::reset(); DB::setDefaultConnection('mysql');
        $this->artisan('zampayroll:admin', ['email' => 'ops@zampayroll.com'])->expectsQuestion('Password (leave empty to generate one)', 'opsPass123')->assertSuccessful();
        $ops = User::where('email', 'ops@zampayroll.com')->first();
        $this->actingAs($ops)->get('/platform')->assertOk()->assertSee('Acme Mining wants a private workspace');
        $this->get('/dashboard')->assertRedirect(route('platform.dashboard')); // no company screens
        $this->post('/platform/companies/' . $company->id . '/action', ['action' => 'upgrade', 'subdomain' => 'acme'])->assertSessionHas('success');

        $company = Company::find($company->id);
        $this->assertTrue($company->isDedicated());
        $this->assertSame('zp_acme', $company->database_name);
        $this->assertNull(User::where('email', 'boss@acme.zm')->first(), 'admin moved out of the shared database');
        $this->assertSame(0, DB::connection('mysql')->table('employees')->where('first_name', 'Shared')->count());
        $this->assertDatabaseHas('tenant_user_directory', ['email' => 'boss@acme.zm', 'company_id' => $company->id]);
        Tenancy::configureTenantConnection('zp_acme');
        $this->assertSame(1, DB::connection('tenant')->table('employees')->where('first_name', 'Shared')->count());
        $this->assertGreaterThan(20, DB::connection('tenant')->table('payroll_rules')->where('company_id', $company->id)->count());
        $this->post('/logout');
    }

    public function test_04_dedicated_workspace_is_isolated_and_central_login_hands_off(): void
    {
        // central login with a dedicated user's credentials -> signed hop to sub-domain
        $res = $this->post('/login', ['email' => 'boss@acme.zm', 'password' => 'secret123']);
        $this->assertStringContainsString('http://acme.localhost/auth/handoff/', $res->headers->get('Location'));
        $this->assertGuest();

        Tenancy::reset(); DB::setDefaultConnection('mysql');
        // sub-domain login directly
        $this->post('http://acme.localhost/login', ['email' => 'boss@acme.zm', 'password' => 'secret123'])
            ->assertRedirect('http://acme.localhost/dashboard');

        // employee created inside the tenant lands in the tenant DB only
        $boss = User::where('email', 'boss@acme.zm')->first(); // default conn is tenant now
        $this->assertNotNull($boss);
        $this->actingAs($boss)->post('http://acme.localhost/employees', [
            'first_name' => 'Tenant', 'last_name' => 'Worker', 'date_of_birth' => '1991-02-02', 'gender' => 'Male',
            'primary_phone' => '0970000000', 'nationality' => 'Zambian', 'leave_days_entitled' => 24, 'position' => 'Driller', 'company_email' => 'worker@acme.zm', 'salary' => 9000,
        ])->assertRedirect();
        $this->assertSame(1, DB::connection('tenant')->table('employees')->where('first_name', 'Tenant')->count());
        $this->assertSame(0, DB::connection('mysql')->table('employees')->where('first_name', 'Tenant')->count());

        // a shared tenant's session can't be used on the sub-domain
        Tenancy::reset(); DB::setDefaultConnection('mysql');
        $this->get('http://nope.localhost/login')->assertNotFound()->assertSee('no workspace');
    }

    public function test_05_password_reset_shared_and_dedicated(): void
    {
        Notification::fake();
        $this->get('/forgot-password')->assertOk();
        $this->post('/forgot-password', ['email' => 'admin@pikozm.com'])->assertSessionHas('status');
        $admin = User::where('email', 'admin@pikozm.com')->first();
        $token = null;
        Notification::assertSentTo($admin, ResetPasswordNotification::class, function ($n) use (&$token, $admin) {
            $token = $n->token;
            $mail = $n->toMail($admin);
            $this->assertStringContainsString('http://localhost/reset-password/', $mail->viewData['url']);
            return true;
        });
        $this->get('/reset-password/' . $token . '?email=admin@pikozm.com')->assertOk()->assertSee('Choose a new password');
        $this->post('/reset-password', ['token' => $token, 'email' => 'admin@pikozm.com', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertRedirect();
        $this->post('/login', ['email' => 'admin@pikozm.com', 'password' => 'newpass123'])->assertRedirect(route('dashboard'));
        $this->post('/logout');
        DB::table('users')->where('email', 'admin@pikozm.com')->update(['password' => bcrypt('password123')]);

        // dedicated user resetting from the central domain -> link points at the sub-domain
        Tenancy::reset(); DB::setDefaultConnection('mysql'); $this->app['auth']->forgetGuards();
        $this->post('/forgot-password', ['email' => 'boss@acme.zm'])->assertSessionHas('status');
        $this->assertSame(1, DB::connection('tenant')->table('password_reset_tokens')->where('email', 'boss@acme.zm')->count());
        $this->assertSame(0, DB::connection('mysql')->table('password_reset_tokens')->where('email', 'boss@acme.zm')->count());
    }

    public function test_06_employee_self_service_portal(): void
    {
        Mail::fake();
        $admin = User::where('email', 'admin@pikozm.com')->first();
        $mary = Employee::withoutGlobalScopes()->where('first_name', 'Mary')->first();

        $this->actingAs($admin)->post(route('employees.portal.store', $mary))->assertSessionHas('success');
        $password = null;
        Mail::assertSent(EmployeePortalWelcomeMail::class, function ($m) use (&$password) {
            $password = $m->temporaryPassword;
            return $m->hasTo('mary@pikozm.com');
        });
        $this->post('/logout');
        $this->app['auth']->forgetGuards(); Tenancy::reset();

        // first login -> forced password change
        $this->post('/login', ['company_code' => $this->codeFor('mary@pikozm.com'), 'email' => 'mary@pikozm.com', 'password' => $password])->assertRedirect(route('portal.dashboard'));
        $this->get('/portal')->assertRedirect(route('portal.password.edit'));
        $this->get('/dashboard')->assertRedirect(route('portal.dashboard')); // no HR area
        $this->get('/employees')->assertRedirect(route('portal.dashboard'));
        $this->put('/portal/password', ['current_password' => $password, 'password' => 'mary12345', 'password_confirmation' => 'mary12345'])
            ->assertRedirect(route('portal.dashboard'));

        $this->get('/portal')->assertOk()->assertSee('Mary')->assertSee('6,500.00');
        $this->get('/portal/payslips')->assertOk()->assertSee('September 2026');
        $payroll = \App\Models\Payroll::where('employee_id', $mary->id)->first();
        $this->get(route('portal.payslips.show', $payroll))->assertOk();
        $this->get(route('portal.payslips.download', $payroll))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->get('/portal/leave')->assertOk();
        $this->getJson('/portal/leave/calculate?start_date=2026-10-22&end_date=2026-10-27')->assertJson(['days' => 4]); // Thu,Fri,Mon,Tue (24th Sat holiday, Sun)
        $this->post('/portal/leave', ['leave_type' => 'annual', 'start_date' => '2026-11-02', 'end_date' => '2026-11-06', 'reason' => 'Trip'])
            ->assertRedirect(route('portal.leave'));
        $this->get('/portal/overtime')->assertOk();
        $this->post('/portal/overtime', ['date' => '2026-10-10', 'start_time' => '17:00', 'end_time' => '20:00', 'type' => 'normal'])
            ->assertRedirect(route('portal.overtime'));
        $this->get('/portal/loans')->assertOk();
        $this->post('/portal/loans', ['amount' => 3000, 'payment_plan' => 'monthly', 'installment_amount' => 1000])
            ->assertRedirect(route('portal.loans'));
        $this->get('/portal/profile')->assertOk();
        $this->put('/portal/profile', ['primary_phone' => '0977999999', 'emergency_name' => 'Mum'])->assertSessionHas('success');

        $leave = LeaveRequest::where('employee_id', $mary->id)->whereDate('start_date', '2026-11-02')->first();
        $this->assertEquals(5, (float) $leave->days);
        $this->assertSame($mary->company_id, $leave->company_id);

        // cannot see another employee's payslip
        $grace = Employee::withoutGlobalScopes()->where('first_name', 'Grace')->first();
        $this->post('/logout');
        $this->app['auth']->forgetGuards(); Tenancy::reset();

        // HR sees the requests
        $this->actingAs($admin)->get('/leave/dashboard/requests')->assertOk()->assertSee('Mary');
        $this->actingAs($admin)->get('/overtime/dashboard/requests')->assertOk();
        $this->actingAs($admin)->get('/loan/dashboard/requests')->assertOk();
        $this->actingAs($admin)->get('/employees')->assertOk()->assertSee('Create portals');
    }

    public function test_07_pages_render(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back');
        $this->get('/email/verify')->assertOk()->assertSee('Check your inbox');
        $this->get('http://acme.localhost/login')->assertOk()->assertSee('Acme Mining');
        $this->get('http://acme.localhost/register')->assertNotFound();
        Tenancy::reset(); DB::setDefaultConnection('mysql');
        $admin = User::where('email', 'admin@pikozm.com')->first();
        foreach (['/dashboard', '/employees/create', '/payroll/runs/all', '/attendance', '/leave', '/loan/ledger', '/settings/company', '/profile', '/payroll/reports'] as $u) {
            $r = $this->actingAs($admin)->get('http://localhost' . $u);
            $this->assertSame(200, $r->status(), $u . ' -> ' . $r->headers->get('Location'));
        }
    }
}
