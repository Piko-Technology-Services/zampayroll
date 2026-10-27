<?php

namespace Tests\Feature;

use App\Mail\PartnerMail;
use App\Models\BillingPayment;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\PartnerCommission;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\Billing\PlatformSettings;
use App\Services\Billing\Subscriptions;
use App\Services\Partners\PartnerProgramme;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Partner programme: apply → code + link → clients linked at sign-up →
 * commission on paid invoices → admin pays out. Partners never see company data.
 */
class PartnerProgrammeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Tenancy::reset();
        DB::setDefaultConnection('mysql');
        Mail::fake();
        Notification::fake();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); // many sign-ups in a minute here
        PlatformSettings::set(['partner_auto_approve' => 1, 'partner_commission_rate' => 20, 'partner_commission_months' => 0]);
    }

    private function apply(string $name, string $email, array $extra = []): Partner
    {
        $this->post('http://localhost/partners', $extra + [
            'name' => $name, 'email' => $email, 'phone' => '0977000111', 'partner_type' => 'hr_practitioner',
            'firm_name' => '', 'city' => 'Lusaka', 'password' => 'partner123', 'password_confirmation' => 'partner123', 'terms' => 1,
        ])->assertRedirect(route('verification.notice'));

        return Partner::where('email', $email)->firstOrFail();
    }

    private function register(string $email, string $companyName, array $extra = [])
    {
        return $this->post('http://localhost/register', $extra + [
            'name' => 'Owner ' . $companyName, 'phone' => '0966000000', 'email' => $email,
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'company_name' => $companyName, 'industry' => 'retail', 'employee_count_range' => '11-50',
            'finish_later' => 1, 'terms' => 1,
        ]);
    }

    private function platformAdmin(): User
    {
        return User::firstOrCreate(['email' => 'ops.partners@zampayroll.com'], [
            'name' => 'Ops Partners', 'password' => bcrypt('opsPass123'), 'role' => 'platform_admin', 'company_id' => null, 'email_verified_at' => now(),
        ]);
    }

    public function test_apply_get_code_verify_and_open_dashboard(): void
    {
        $this->get('http://localhost/partners')->assertOk()->assertSee('Know HR? Earn from it every month.')->assertSee('HR student');

        // Email already used by a company account → refused
        $this->post('http://localhost/partners', ['name' => 'X', 'email' => 'admin@pikozm.com', 'phone' => '1', 'partner_type' => 'freelancer',
            'password' => 'partner123', 'password_confirmation' => 'partner123', 'terms' => 1])->assertSessionHasErrors('email');

        $partner = $this->apply('Chanda Mwila', 'chanda.partner@hrpro.co.zm');
        $this->assertMatchesRegularExpression('/^ZP-CHANDA\d{2}$/', $partner->code);
        $this->assertSame('active', $partner->status);
        $user = $partner->user;
        $this->assertSame('partner', $user->role);
        $this->assertNull($user->company_id);
        $this->assertNull($user->email_verified_at);
        Mail::assertSent(PartnerMail::class, fn ($m) => $m->kind === 'welcome' && $m->hasTo('chanda.partner@hrpro.co.zm'));

        // Confirm email → partner dashboard
        $url = null;
        Notification::assertSentTo($user, VerifyEmailNotification::class, function ($n) use ($user, &$url) {
            $url = $n->verificationUrl($user);

            return true;
        });
        $this->get($url)->assertRedirect(route('partner.dashboard'));
        $this->get('http://localhost/partner')->assertOk()->assertSee($partner->code)->assertSee($partner->joinUrl())->assertSee('No clients yet');

        // Partners never reach company screens or the admin console
        $this->get('http://localhost/dashboard')->assertRedirect(route('partner.dashboard'));
        $this->get('http://localhost/employees')->assertRedirect(route('partner.dashboard'));
        $this->get('http://localhost/portal')->assertRedirect();
        $this->get('http://localhost/platform')->assertNotFound();

        // Sign in later with just email + password
        $this->post('http://localhost/logout');
        $this->post('http://localhost/login', ['email' => 'chanda.partner@hrpro.co.zm', 'password' => 'partner123'])->assertRedirect(route('partner.dashboard'));

        // Payout details
        $this->post('http://localhost/partner/payout', ['payout_method' => 'mobile_money', 'payout_details' => 'Airtel 0977 123 456'])->assertSessionHas('success');
        $this->assertSame('Airtel 0977 123 456', $partner->fresh()->payout_details);

        // Company users can't open the partner dashboard
        $this->post('http://localhost/logout');
        $this->actingAs(User::where('email', 'admin@pikozm.com')->first())->get('http://localhost/partner')->assertRedirect(route('dashboard'));
    }

    public function test_code_and_link_link_new_companies_to_the_partner(): void
    {
        $partner = $this->apply('Mutale Banda', 'mutale.partner@hr.zm', ['partner_type' => 'hr_firm', 'firm_name' => 'Banda HR Consulting']);

        // Live check on the sign-up form
        $this->getJson('http://localhost/partner-code/check?code=' . strtolower($partner->code))->assertJson(['valid' => true, 'name' => 'Banda HR Consulting']);
        $this->getJson('http://localhost/partner-code/check?code=ZP-NOBODY00')->assertJson(['valid' => false]);

        // The link remembers the partner and fills in the code
        $this->get('http://localhost/join/' . $partner->code)->assertRedirect(route('register', ['partner' => $partner->code]))->assertCookie(PartnerProgramme::COOKIE);
        $this->get('http://localhost/register?partner=' . $partner->code)->assertOk()->assertSee('Linked to partner: Banda HR Consulting');
        $this->get('http://localhost/join/ZP-NOBODY00')->assertRedirect(route('register'));

        // Typed code
        $this->register('owner@typed.zm', 'Typed Code Ltd', ['partner_code' => strtolower($partner->code)])->assertRedirect(route('verification.notice'));
        $typed = Company::where('name', 'Typed Code Ltd')->firstOrFail();
        $this->assertSame([$partner->id, $partner->code], [(int) $typed->partner_id, $typed->partner_code]);
        $this->assertNotNull($typed->referred_at);
        Mail::assertSent(PartnerMail::class, fn ($m) => $m->kind === 'new_client' && ($m->data['company'] ?? null) === 'Typed Code Ltd');

        // Wrong code → friendly error, nothing created
        $this->register('owner@wrong.zm', 'Wrong Code Ltd', ['partner_code' => 'ZP-NOBODY00'])->assertSessionHasErrors('partner_code');
        $this->assertFalse(Company::where('name', 'Wrong Code Ltd')->exists());

        // Link only (cookie, nothing typed)
        $this->withCookie(PartnerProgramme::COOKIE, $partner->code)->register('owner@link.zm', 'Via Link Ltd')->assertRedirect(route('verification.notice'));
        $this->assertSame($partner->id, (int) Company::where('name', 'Via Link Ltd')->value('partner_id'));

        // No code, no cookie → not linked
        $this->defaultCookies = [];
        $this->register('owner@direct.zm', 'Direct Ltd')->assertRedirect(route('verification.notice'));
        $this->assertNull(Company::where('name', 'Direct Ltd')->value('partner_id'));

        // New companies start on the free plan
        $this->assertSame('free', $typed->subscription_status);

        // Dashboard lists the clients (name, size, plan only)
        $this->actingAs($partner->user->fresh())->get('http://localhost/partner')->assertOk()
            ->assertSee('Typed Code Ltd')->assertSee('Via Link Ltd')->assertDontSee('Direct Ltd')->assertSee('Free plan');

        // Suspended partner: code stops working
        $this->post('http://localhost/logout');
        $partner->update(['status' => 'suspended']);
        $this->getJson('http://localhost/partner-code/check?code=' . $partner->code)->assertJson(['valid' => false]);
        $this->register('owner@late.zm', 'Late Ltd', ['partner_code' => $partner->code])->assertSessionHasErrors('partner_code');
    }

    public function test_commission_on_paid_invoices_and_admin_payout(): void
    {
        $partner = $this->apply('Grace Tembo', 'grace.partner@hr.zm', ['partner_type' => 'freelancer']);
        $this->register('owner@paying.zm', 'Paying Client Ltd', ['partner_code' => $partner->code]);
        $company = Company::where('name', 'Paying Client Ltd')->firstOrFail();
        $subs = app(Subscriptions::class);

        // The client pays a K 1,000 invoice → 20% commission, once
        $invoice = $subs->createInvoice($company, 'usage', [['description' => 'Business — 25 employees', 'quantity' => 25, 'unit' => 40, 'amount' => 1000]], now()->subMonth(), now(), now()->addDays(7));
        $pay = fn () => BillingPayment::withoutGlobalScopes()->create(['company_id' => $company->id, 'invoice_id' => $invoice->id, 'provider' => 'manual', 'method' => 'bank', 'amount' => 1000, 'status' => 'pending', 'reference' => 'T1']);
        $this->assertTrue($subs->markPaid($invoice, $pay()));
        $this->assertFalse($subs->markPaid($invoice, $pay()), 'second payment is not double counted');
        $rows = PartnerCommission::where('partner_id', $partner->id)->get();
        $this->assertCount(1, $rows);
        $this->assertEquals(200, (float) $rows[0]->amount);
        $this->assertSame('pending', $rows[0]->status);

        $this->actingAs($partner->user->fresh())->get('http://localhost/partner')->assertOk()->assertSee('K 200.00')->assertSee($invoice->number)->assertSee('Paying Client Ltd');

        // Custom rate applies to later invoices
        $ops = $this->platformAdmin();
        $this->actingAs($ops)->post('http://localhost/platform/partners/' . $partner->id . '/action', ['action' => 'rate', 'commission_rate' => 25])->assertSessionHas('success');
        $inv2 = $subs->createInvoice($company, 'other', [['description' => 'Onboarding', 'quantity' => 1, 'unit' => 400, 'amount' => 400]], null, null, now()->addDays(7));
        $subs->markPaid($inv2, BillingPayment::withoutGlobalScopes()->create(['company_id' => $company->id, 'invoice_id' => $inv2->id, 'provider' => 'manual', 'method' => 'cash', 'amount' => 400, 'status' => 'pending', 'reference' => 'T2']));
        $this->assertEquals(100, (float) PartnerCommission::where('invoice_id', $inv2->id)->value('amount'));

        // Admin console: list, detail, pay out
        $this->get('http://localhost/platform/partners')->assertOk()->assertSee($partner->code)->assertSee('K 300.00');
        $this->get('http://localhost/platform/partners/' . $partner->id)->assertOk()->assertSee('Paying Client Ltd')->assertSee('Mark ticked as paid');
        $this->get('http://localhost/platform/companies/' . $company->id)->assertOk()->assertSee('Referred by');
        $ids = PartnerCommission::where('partner_id', $partner->id)->pluck('id')->all();
        $this->post('http://localhost/platform/partners/' . $partner->id . '/action', ['action' => 'mark_paid', 'ids' => $ids, 'reference' => 'AIRTEL-8H2K'])->assertSessionHas('success');
        $this->assertSame(['paid'], PartnerCommission::where('partner_id', $partner->id)->pluck('status')->unique()->values()->all());
        Mail::assertSent(PartnerMail::class, fn ($m) => $m->kind === 'paid' && (float) $m->data['amount'] === 300.0);

        // No commission for companies without a partner, or after the commission period
        $direct = Company::where('name', 'Second Co')->first();
        $inv3 = $subs->createInvoice($direct, 'other', [['description' => 'x', 'quantity' => 1, 'unit' => 100, 'amount' => 100]], null, null, now());
        $subs->markPaid($inv3, BillingPayment::withoutGlobalScopes()->create(['company_id' => $direct->id, 'invoice_id' => $inv3->id, 'provider' => 'manual', 'method' => 'cash', 'amount' => 100, 'status' => 'pending', 'reference' => 'T3']));
        $this->assertFalse(PartnerCommission::where('invoice_id', $inv3->id)->exists());

        PlatformSettings::set(['partner_commission_months' => 12]);
        $company->forceFill(['referred_at' => now()->subMonths(13)])->save();
        $inv4 = $subs->createInvoice($company, 'other', [['description' => 'y', 'quantity' => 1, 'unit' => 100, 'amount' => 100]], null, null, now());
        $subs->markPaid($inv4, BillingPayment::withoutGlobalScopes()->create(['company_id' => $company->id, 'invoice_id' => $inv4->id, 'provider' => 'manual', 'method' => 'cash', 'amount' => 100, 'status' => 'pending', 'reference' => 'T4']));
        $this->assertFalse(PartnerCommission::where('invoice_id', $inv4->id)->exists());
        PlatformSettings::set(['partner_commission_months' => 0]);
        Invoice::withoutGlobalScopes()->where('id', $inv3->id)->delete();
        BillingPayment::withoutGlobalScopes()->where('invoice_id', $inv3->id)->delete();

        // Admin can move a company to another partner, or remove the link
        $this->post('http://localhost/platform/companies/' . $company->id . '/action', ['action' => 'set_partner', 'partner_code' => ''])->assertSessionHas('success');
        $this->assertNull($company->fresh()->partner_id);
        $this->post('http://localhost/platform/companies/' . $company->id . '/action', ['action' => 'set_partner', 'partner_code' => 'ZP-NOBODY00'])->assertSessionHas('error');
    }

    public function test_review_first_when_auto_approve_is_off(): void
    {
        PlatformSettings::set(['partner_auto_approve' => 0]);
        $partner = $this->apply('Bwalya Zulu', 'bwalya.student@unza.zm', ['partner_type' => 'hr_student']);
        $this->assertSame('pending', $partner->status);
        Mail::assertSent(PartnerMail::class, fn ($m) => $m->kind === 'received');
        $this->assertNull(app(PartnerProgramme::class)->findActive($partner->code));
        $this->actingAs($partner->user)->get('http://localhost/partner')->assertOk()->assertSee('Your application is being reviewed');

        $this->actingAs($this->platformAdmin())->get('http://localhost/platform/partners?status=pending')->assertOk()->assertSee('Bwalya Zulu');
        $this->post('http://localhost/platform/partners/' . $partner->id . '/action', ['action' => 'approve'])->assertSessionHas('success');
        $this->assertSame('active', $partner->fresh()->status);
        Mail::assertSent(PartnerMail::class, fn ($m) => $m->kind === 'approved');
        $this->assertNotNull(app(PartnerProgramme::class)->findActive($partner->code));
        PlatformSettings::set(['partner_auto_approve' => 1]);
    }

    public function test_public_pages_mention_partners_and_free_plan(): void
    {
        $this->get('http://localhost/pricing')->assertOk()->assertSee('Become a partner')->assertSee('7-day free trial starts when you add employee 11');
        $this->get('http://localhost/')->assertOk()->assertSee(route('partners.show'));
        $this->get('http://localhost/sitemap.xml')->assertOk()->assertSee('/partners');
    }
}
