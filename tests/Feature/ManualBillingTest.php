<?php

namespace Tests\Feature;

use App\Mail\BillingMail;
use App\Mail\BillingProofAdminMail;
use App\Models\BillingPayment;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\Plans;
use App\Services\Billing\PlatformSettings;
use App\Services\Billing\Subscriptions;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualBillingTest extends TestCase
{
    private static int $seq = 0;

    /** Each call registers a brand-new company, so the shared baseline company is never changed. */
    private function companyAdmin(): User
    {
        $n = ++self::$seq;
        $email = "billing.owner{$n}@zampayroll.test";
        $name = "Billing Test Co {$n}";

        $this->post('http://localhost/register', [
            'name' => 'Owner ' . $name, 'phone' => '0966000000', 'email' => $email,
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'company_name' => $name, 'industry' => 'retail', 'employee_count_range' => '11-50',
            'finish_later' => 1, 'terms' => 1,
        ])->assertRedirect();

        Tenancy::reset(); DB::setDefaultConnection('mysql');
        $user = User::where('email', $email)->firstOrFail();
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function platformAdmin(): User
    {
        return User::firstOrCreate(['email' => 'billing.ops@zampayroll.com'], [
            'name' => 'Billing Ops', 'password' => bcrypt('opsPass123'), 'role' => 'platform_admin', 'company_id' => null, 'email_verified_at' => now(),
        ]);
    }

    public function test_plans_follow_team_size_and_enterprise_is_by_consultation(): void
    {
        $this->assertSame('tikula', Plans::forEmployees(1));
        $this->assertSame('tikula', Plans::forEmployees(10));
        $this->assertSame('tikula', Plans::forEmployees(50));
        $this->assertSame('medium', Plans::forEmployees(51));
        $this->assertSame('medium', Plans::forEmployees(150));
        $this->assertSame('corporate', Plans::forEmployees(151));
        $this->assertSame('corporate', Plans::forEmployees(300));
        $this->assertNull(Plans::forEmployees(301));
        $this->assertEquals(799, Plans::price('tikula', 'monthly'));
        $this->assertEquals(7990, Plans::price('tikula', 'annual'));
        $this->assertEquals(1499, Plans::price('medium', 'monthly'));
        $this->assertEquals(24990, Plans::price('corporate', 'annual'));
    }

    public function test_pricing_page_shows_the_plans_and_free_experience(): void
    {
        $this->get('http://localhost/pricing')->assertOk()
            ->assertSee('One powerful platform. Every tool your business needs.')
            ->assertSee('Free Experience')->assertSee('for 2 months')
            ->assertSee('Tikula')->assertSee('K799')->assertSee('K7,990')
            ->assertSee('Medium Enterprise')->assertSee('K1,499')
            ->assertSee('Corporate')->assertSee('K2,499')
            ->assertSee('Tailored Pricing')
            ->assertSee('Start with confidence. Scale with intelligence. Manage your people with ZamPayroll.');
    }

    public function test_new_company_gets_two_months_free(): void
    {
        $company = Company::on(Tenancy::landlordConnection())->find($this->companyAdmin()->company_id);
        $company->forceFill(['subscription_status' => 'free', 'trial_ends_at' => null, 'billing_type' => null])->save();

        app(Subscriptions::class)->syncFreeTier($company->fresh());

        $company->refresh();
        $this->assertSame('trialing', $company->subscription_status);
        $this->assertEqualsWithDelta(60 * 24, now()->diffInHours($company->trial_ends_at, false) > 0 ? now()->diffInHours($company->trial_ends_at) : 0, 24 * 2);
        $this->assertTrue($company->trial_ends_at->greaterThan(now()->addMonthsNoOverflow(2)->subDay()));
    }

    public function test_manual_payment_from_invoice_to_admin_confirmation(): void
    {
        Mail::fake();
        Storage::fake('local');
        $admin = $this->companyAdmin();
        $company = Company::on(Tenancy::landlordConnection())->find($admin->company_id);
        $company->forceFill(['subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(10), 'billing_type' => null, 'billing_email' => null])->save();
        $tier = Plans::forEmployees(app(Subscriptions::class)->activeEmployees($company));

        // 1. Choose a plan on the billing page → the checkout page, with Manual invoice selected by default
        $this->actingAs($admin)->get(route('billing.index'))->assertOk()
            ->assertSee('Pay for your ZamPayroll subscription')->assertSee('Pay for this plan')->assertSee('or K7,990 a year')
            ->assertSee('Built for ambitious businesses ready to professionalise their HR operations and simplify payroll.')
            ->assertDontSee('How to pay manually');
        $this->get(route('billing.checkout.form', ['plan' => $tier, 'cycle' => 'monthly']))->assertOk()
            ->assertSee('Your details')->assertSee('Email for your receipt')->assertSee('Manual invoice')->assertSee('Mobile money or card')
            ->assertSee('value="manual" checked', false);

        // 2. Details + manual → invoice created and emailed straight away, back to billing to pay
        $r = $this->post(route('billing.checkout'), ['plan' => $tier, 'cycle' => 'monthly', 'method' => 'manual', 'contact_name' => 'Agnes Banda', 'billing_email' => 'accounts@acme.test']);
        $invoice = Invoice::withoutGlobalScopes()->where('company_id', $company->id)->where('kind', 'subscription')->latest('id')->firstOrFail();
        $r->assertRedirect(route('billing.index') . '#pay');
        $this->assertSame('manual', $invoice->meta['method']);
        $this->assertSame($tier, $invoice->meta['plan']);
        $this->assertSame('Agnes Banda', $invoice->meta['contact_name']);
        $this->assertEquals(Plans::price($tier, 'monthly'), (float) $invoice->total);
        $this->assertSame('accounts@acme.test', $company->fresh()->billing_email);
        Mail::assertSent(BillingMail::class, fn ($m) => $m->kind === 'invoice' && $m->invoice?->id === $invoice->id);
        $this->get(route('billing.index'))->assertOk()->assertSee($invoice->number)->assertSee('Upload your proof of payment')
            ->assertSee('63216469741')->assertSee('Piko Technology Services')->assertSee('Ndola Main Branch');

        // A manual checkout needs an email for the invoice
        $this->post(route('billing.checkout'), ['plan' => $tier, 'cycle' => 'monthly', 'method' => 'manual', 'billing_email' => ''])->assertSessionHasErrors('billing_email');

        // 3. Proof of payment uploaded → admin is emailed, company gets a receipt of proof
        $proof = UploadedFile::fake()->create('transfer.pdf', 120, 'application/pdf');
        $this->actingAs($admin)->post(route('billing.invoices.proof', $invoice), ['proof' => $proof, 'reference' => 'BANK-7781'])
            ->assertRedirect(route('billing.index'))->assertSessionHas('success');
        $payment = BillingPayment::withoutGlobalScopes()->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('awaiting_review', $payment->status);
        $this->assertNotNull($payment->proof_path);
        Storage::disk('local')->assertExists($payment->proof_path);
        Mail::assertSent(BillingProofAdminMail::class);
        Mail::assertSent(BillingMail::class, fn ($m) => $m->kind === 'proof_received');

        // A second upload while one is being checked is refused
        $this->actingAs($admin)->post(route('billing.invoices.proof', $invoice), ['proof' => UploadedFile::fake()->create('again.pdf', 10, 'application/pdf')])
            ->assertSessionHas('error');

        // 4. Admin declines with a reason → company emailed, invoice still open
        $platform = $this->platformAdmin();
        // The admin console lists the proof to check, and the invoice PDF renders with the bank details
        $this->actingAs($platform)->get(route('platform.invoices'))->assertOk()->assertSee('Payment proofs to check')->assertSee($invoice->number);
        $this->actingAs($admin)->get(route('billing.invoices.pdf', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($platform)->post(route('platform.proofs.decline', $payment), ['reason' => 'The amount is K500, not the invoice total.'])->assertRedirect();
        $this->assertSame('declined', $payment->fresh()->status);
        $this->assertSame('open', $invoice->fresh()->status);
        Mail::assertSent(BillingMail::class, fn ($m) => $m->kind === 'proof_declined' && ($m->data['reason'] ?? null) === 'The amount is K500, not the invoice total.');

        // 5. A corrected proof is sent and the admin confirms the money arrived → plan active
        $this->actingAs($admin)->post(route('billing.invoices.proof', $invoice), ['proof' => UploadedFile::fake()->create('fixed.pdf', 20, 'application/pdf')])->assertSessionHas('success');
        $second = BillingPayment::withoutGlobalScopes()->where('invoice_id', $invoice->id)->where('status', 'awaiting_review')->firstOrFail();
        $this->actingAs($platform)->get(route('platform.proofs.file', $second))->assertOk();
        $this->actingAs($platform)->post(route('platform.proofs.confirm', $second))->assertRedirect();

        $this->assertSame('paid', $invoice->fresh()->status);
        $company->refresh();
        $this->assertSame(['active', 'prepaid', $tier], [$company->subscription_status, $company->billing_type, $company->plan]);
        $this->assertTrue($company->paid_until->isFuture());
        $this->assertSame('paid', $second->fresh()->status);
        $this->assertSame('bank_transfer', $second->fresh()->method);
        Mail::assertSent(BillingMail::class, fn ($m) => $m->kind === 'receipt');

        // The company's proof file is private to ZamPayroll admins
        $this->actingAs($admin)->get(route('platform.proofs.file', $second))->assertNotFound();
    }

    public function test_online_checkout_sends_customer_to_the_payment_page(): void
    {
        $admin = $this->companyAdmin();
        $company = Company::on(Tenancy::landlordConnection())->find($admin->company_id);
        $company->forceFill(['subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(10), 'billing_type' => null])->save();
        $tier = Plans::forEmployees(app(Subscriptions::class)->activeEmployees($company));

        $response = $this->actingAs($admin)->post('http://localhost/settings/billing/checkout', ['plan' => $tier, 'cycle' => 'annual', 'method' => 'online']);
        $invoice = Invoice::withoutGlobalScopes()->where('company_id', $company->id)->latest('id')->firstOrFail();

        $response->assertRedirect();
        $target = $response->headers->get('Location');
        $this->assertStringStartsWith(config('billing.online_payment_url') . '/checkout/' . Plans::TIERS[$tier]['slug'] . '?billing=annual', $target);
        $this->assertStringContainsString('invoice=' . $invoice->number, $target);
        $this->assertSame('online', $invoice->meta['method']);
    }

    public function test_any_plan_can_be_paid_and_renewal_keeps_it(): void
    {
        $admin = $this->companyAdmin();
        $company = Company::on(Tenancy::landlordConnection())->find($admin->company_id);
        $company->forceFill(['subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(5), 'billing_type' => null])->save();
        $needed = Plans::forEmployees(app(Subscriptions::class)->activeEmployees($company));
        $other = $needed === 'corporate' ? 'tikula' : 'corporate';

        // A team that fits one plan can still choose a different one
        Mail::fake();
        $this->actingAs($admin)->post('http://localhost/settings/billing/checkout', ['plan' => $other, 'cycle' => 'monthly', 'method' => 'manual', 'billing_email' => 'pay@acme.test'])
            ->assertSessionMissing('error');
        $this->assertSame(1, Invoice::withoutGlobalScopes()->where('company_id', $company->id)->where('meta->plan', $other)->count());

        // Renewals keep the plan the company is on, not the plan for its team size
        $company->forceFill(['plan' => $other, 'billing_type' => 'prepaid', 'subscription_status' => 'active', 'paid_until' => now()->addDays(20)])->save();
        $renewal = app(Subscriptions::class)->renewalInvoice($company->fresh(), 'monthly');
        $this->assertSame($other, $renewal->meta['plan']);
    }

    public function test_small_team_on_the_free_experience_can_subscribe_to_any_plan(): void
    {
        Mail::fake();
        Storage::fake('local');
        $admin = $this->companyAdmin();
        $company = Company::on(Tenancy::landlordConnection())->find($admin->company_id);
        $company->forceFill(['subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(40), 'billing_type' => null])->save();
        $this->assertLessThanOrEqual(10, app(Subscriptions::class)->activeEmployees($company));

        $this->actingAs($admin)->post(route('billing.checkout'), ['plan' => 'medium', 'cycle' => 'annual', 'method' => 'manual', 'billing_email' => 'pay@small.test'])
            ->assertSessionMissing('error');
        $invoice = Invoice::withoutGlobalScopes()->where('company_id', $company->id)->where('meta->plan', 'medium')->firstOrFail();
        $this->assertEquals(Plans::price('medium', 'annual'), (float) $invoice->total);

        $this->post(route('billing.invoices.proof', $invoice), ['proof' => UploadedFile::fake()->create('pop.pdf', 30, 'application/pdf')])->assertSessionHas('success');
        $payment = BillingPayment::withoutGlobalScopes()->where('invoice_id', $invoice->id)->firstOrFail();
        app(Subscriptions::class)->confirmProof($payment, null);

        $company->refresh();
        $this->assertSame(['active', 'prepaid', 'medium'], [$company->subscription_status, $company->billing_type, $company->plan]);
    }

    public function test_legacy_post_paid_cannot_be_started_from_billing(): void
    {
        $admin = $this->companyAdmin();
        $this->actingAs($admin)->post('http://localhost/settings/billing/postpaid', ['accept' => 1])->assertSessionHas('error');
    }
}
