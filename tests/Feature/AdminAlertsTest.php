<?php

namespace Tests\Feature;

use App\Mail\AdminAlertMail;
use App\Mail\SupportTicketMail;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** admin@zampayroll.com hears about sign-ups, tickets, partner applications and billing contact changes. */
class AdminAlertsTest extends TestCase
{
    private const ADMIN = 'admin@zampayroll.com';

    private function signUp(string $email, string $company): User
    {
        $this->post('http://localhost/register', [
            'name' => 'Owner of ' . $company, 'phone' => '0966111222', 'email' => $email,
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'company_name' => $company, 'industry' => 'retail', 'employee_count_range' => '6-10',
            'city' => 'Lusaka', 'finish_later' => 1, 'terms' => 1,
        ])->assertRedirect(route('verification.notice'));

        Tenancy::reset(); DB::setDefaultConnection('mysql');
        $user = User::where('email', $email)->firstOrFail();
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    public function test_new_company_sign_up_alerts_admin(): void
    {
        Mail::fake();
        $this->signUp('owner@alertco.zm', 'Alert Co Ltd');

        Mail::assertSent(AdminAlertMail::class, fn ($m) => $m->hasTo(self::ADMIN)
            && str_contains($m->alertSubject, 'New company sign-up: Alert Co Ltd')
            && $m->rows['Email'] === 'owner@alertco.zm' && $m->rows['Industry'] === 'Retail & Trade');

        $mail = new AdminAlertMail('s', 'e', 'Heading here', 'Intro here', ['Company' => 'Alert Co Ltd', 'Empty' => null], 'https://zampayroll.com', 'View company');
        $html = $mail->render();
        $this->assertStringContainsString('Alert Co Ltd', $html);
        $this->assertStringNotContainsString('Empty', $html);
    }

    public function test_partner_registration_alerts_admin(): void
    {
        Mail::fake();
        $this->post('http://localhost/partners', [
            'name' => 'Bwalya Partner', 'email' => 'bwalya.partner@hr.zm', 'phone' => '0977000111',
            'partner_type' => 'hr_practitioner', 'city' => 'Ndola',
            'password' => 'partner123', 'password_confirmation' => 'partner123', 'terms' => 1,
        ])->assertRedirect(route('verification.notice'));

        Mail::assertSent(AdminAlertMail::class, fn ($m) => $m->hasTo(self::ADMIN)
            && $m->alertSubject === 'New partner registration: Bwalya Partner' && $m->rows['Type'] === 'HR practitioner');
    }

    public function test_support_tickets_and_replies_copy_admin(): void
    {
        $admin = $this->signUp('owner@ticketco.zm', 'Ticket Co Ltd');
        Mail::fake();

        $this->actingAs($admin)->post('http://localhost/help', ['subject' => 'Payslip question', 'category' => 'question', 'body' => 'How do I resend a payslip?'])
            ->assertSessionHas('success');
        Mail::assertSent(SupportTicketMail::class, fn ($m) => $m->hasTo(config('billing.support_email')) && $m->hasCc(self::ADMIN));
    }

    public function test_billing_contact_change_alerts_admin_only_when_it_changes(): void
    {
        $admin = $this->signUp('owner@payco.zm', 'Pay Co Ltd');
        Mail::fake();

        $this->actingAs($admin)->post('http://localhost/settings/billing/details', ['billing_email' => 'accounts@payco.zm'])->assertSessionHas('success');
        Mail::assertSent(AdminAlertMail::class, fn ($m) => $m->hasTo(self::ADMIN)
            && $m->alertSubject === 'Billing contact updated: Pay Co Ltd'
            && $m->rows['New email'] === 'accounts@payco.zm' && $m->rows['Previous email'] === '(none)');

        Mail::fake();
        $this->actingAs($admin)->post('http://localhost/settings/billing/details', ['billing_email' => 'Accounts@PayCo.zm'])->assertSessionHas('success');
        Mail::assertNotSent(AdminAlertMail::class);

        // Changing it at checkout counts too
        $this->actingAs($admin)->post('http://localhost/settings/billing/checkout', ['plan' => 'tikula', 'cycle' => 'monthly', 'method' => 'manual', 'contact_name' => 'Ruth Phiri', 'billing_email' => 'finance@payco.zm'])
            ->assertSessionMissing('error');
        Mail::assertSent(AdminAlertMail::class, fn ($m) => $m->rows['New email'] === 'finance@payco.zm' && $m->rows['Contact name'] === 'Ruth Phiri');
    }

    public function test_dashboard_has_billing_quick_action_and_small_ai_button(): void
    {
        $admin = $this->signUp('owner@dashco.zm', 'Dash Co Ltd');
        $this->actingAs($admin)->get('http://localhost/dashboard')->assertOk()
            ->assertSee('class="hrd-card qa-btn"', false)->assertSee(route('billing.index'), false)
            ->assertSeeInOrder(['Home', 'Team', 'Departments', 'Attendance', 'Leave', 'Payroll', 'Taxes & Statutory Returns', 'Billing', 'My subscription & payment plans', 'Ask AI', 'Help', 'Company Settings']);
        $this->assertStringContainsString('Minimised launcher', file_get_contents(public_path('assets/css/zp-ai.css')));
    }
}
