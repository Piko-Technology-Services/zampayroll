<?php

namespace Tests\Feature;

use App\Models\BillingPayment;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\Subscriptions;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BillingTest extends TestCase
{
    private function admin(): User
    {
        Tenancy::reset(); DB::setDefaultConnection('mysql');
        config(['dpo.fake' => true]);
        return User::where('email', 'admin@pikozm.com')->first();
    }

    public function test_login_needs_only_email_and_password(): void
    {
        $this->post('http://localhost/login', ['email' => 'admin@pikozm.com', 'password' => 'password123'])->assertRedirect(route('dashboard'));
        $this->post('http://localhost/logout');
        $this->post('http://localhost/login', ['email' => 'admin@pikozm.com', 'password' => 'wrong'])->assertSessionHasErrors('email');

        // A company code is still accepted (and must match)
        $admin = $this->admin();
        $this->post('http://localhost/login', ['email' => 'admin@pikozm.com', 'password' => 'password123', 'company_code' => $admin->company->company_code])->assertRedirect(route('dashboard'));
        $this->post('http://localhost/logout');
        $this->get('http://localhost/login')->assertOk()->assertDontSee('name="company_code"', false);
    }

    public function test_free_plan_then_trial_from_employee_eleven_then_business_and_payment(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $company = Company::find($admin->company_id);
        $subs = app(Subscriptions::class);
        $this->assertSame('free', $company->subscription_status);
        $this->assertNull($company->trial_ends_at);
        $start = $subs->activeEmployees($company);
        $this->assertLessThanOrEqual(10, $start);

        // Free plan: no trial banner, billing page shows the free plan
        $this->actingAs($admin)->get('http://localhost/employees')->assertOk()->assertDontSee('left in your free trial');
        $this->get('http://localhost/settings/billing')->assertOk()->assertSee('Free plan')->assertSee('Your plan')->assertDontSee('Choose Starter');

        // Up to 10 employees: still free
        for ($i = $start + 1; $i <= 10; $i++) {
            $this->post('http://localhost/employees', ['first_name' => 'Free' . $i, 'last_name' => 'Plan', 'net_salary' => 4000])->assertRedirect();
        }
        $this->assertSame('free', $company->fresh()->subscription_status);
        $this->get('http://localhost/dashboard')->assertSee('10 of 10 free employees used');

        // Employee 11 → the 7-day trial starts by itself
        $this->post('http://localhost/employees', ['first_name' => 'Eleven', 'last_name' => 'Plan', 'net_salary' => 4000])->assertRedirect();
        $company->refresh();
        $this->assertSame('trialing', $company->subscription_status);
        $this->assertEqualsWithDelta(7 * 24, now()->diffInHours($company->trial_ends_at), 1);
        Mail::assertSent(\App\Mail\BillingMail::class, fn ($m) => $m->kind === 'trial_started');
        $this->get('http://localhost/employees')->assertSee('left in your free trial');
        $trialEnds = $company->trial_ends_at->copy();

        // Back to 10 → free again; growing again does not restart the trial
        $eleven = \App\Models\Employee::where('first_name', 'Eleven')->first();
        $eleven->update(['employment_status' => 'Inactive']);
        $this->assertSame('free', $company->fresh()->subscription_status);
        $eleven->update(['employment_status' => 'Active']);
        $company->refresh();
        $this->assertSame('trialing', $company->subscription_status);
        $this->assertTrue($company->trial_ends_at->eq($trialEnds), 'trial is used once, not restarted');

        // Trial over → read-only (viewing still works)
        $company->forceFill(['trial_ends_at' => now()->subDay()])->save();
        $this->post('http://localhost/employees', ['first_name' => 'X', 'last_name' => 'Y'])->assertRedirect(route('billing.index'));
        $this->get('http://localhost/employees')->assertOk();
        $this->get('http://localhost/settings/billing')->assertOk()->assertSee('Start Business plan');

        // Start Business (post-paid) → unlocked
        $this->post('http://localhost/settings/billing/postpaid', ['accept' => 1])->assertRedirect();
        $company->refresh();
        $this->assertSame(['active', 'postpaid', 'business'], [$company->subscription_status, $company->billing_type, $company->plan]);
        $this->post('http://localhost/employees', ['first_name' => 'Paid', 'last_name' => 'Up'])->assertRedirect();
        $this->assertSame('active', $company->fresh()->subscription_status, 'paying companies are not touched by employee changes');

        // A month later: invoiced for actual headcount, paid by mobile money (DPO fake mode)
        $company->forceFill(['billing_anchor' => now()->subMonth()->subDay()->toDateString(), 'last_billed_at' => null])->save();
        $subs->runDaily();
        $invoice = Invoice::where('company_id', $company->id)->where('kind', 'usage')->firstOrFail();
        $this->assertEquals(500, (float) $invoice->total); // 12 × K35 = K420 → minimum K500
        $r = $this->postJson('http://localhost/settings/billing/invoices/' . $invoice->id . '/mobile', ['network' => 'airtel', 'phone' => '0977123456'])->assertOk()->json();
        $this->getJson($r['status_url'])->assertOk()->assertJson(['status' => 'paid']);
        $this->assertSame('paid', $invoice->fresh()->status);
        Mail::assertSent(\App\Mail\BillingMail::class, fn ($m) => $m->kind === 'receipt');
        $this->postJson('http://localhost/settings/billing/invoices/' . $invoice->id . '/card')->assertStatus(422);
        $this->get('http://localhost/settings/billing/invoices/' . $invoice->id . '/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');

        // Repeated DPO notifications are harmless
        $p = BillingPayment::where('invoice_id', $invoice->id)->first();
        $this->post('http://localhost/billing/dpo/notify', ['TransactionToken' => $p->trans_token])->assertOk();
        $this->assertSame(1, BillingPayment::where('invoice_id', $invoice->id)->where('status', 'paid')->count());

        // Another company can't see the invoice
        $this->actingAs(User::where('email', 'admin@second.com')->first())->get('http://localhost/settings/billing/invoices/' . $invoice->id . '/pdf')->assertNotFound();

        // Back to 10 active employees → returns to Free at the next bill date instead of being invoiced
        \App\Models\Employee::withoutGlobalScopes()->where('company_id', $company->id)->whereIn('first_name', ['Eleven', 'Paid'])->update(['employment_status' => 'Inactive']);
        $this->assertSame(10, $subs->activeEmployees($company));
        $company->forceFill(['last_billed_at' => now()->subMonth()->subDay()])->save();
        $subs->runDaily();
        $company->refresh();
        $this->assertSame(['free', null, null], [$company->subscription_status, $company->billing_type, $company->plan]);
        $this->assertSame(1, Invoice::withoutGlobalScopes()->where('company_id', $company->id)->where('kind', 'usage')->count());

        // Clean up for other tests
        \App\Models\Employee::withoutGlobalScopes()->where('company_id', $company->id)->where(fn ($q) => $q->where('last_name', 'Plan')->orWhere('first_name', 'Paid'))->delete();
        $company->forceFill(['subscription_status' => 'free', 'trial_ends_at' => null])->save();
    }

    public function test_postpaid_invoices_and_overdue_limit(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $company = Company::where('name', 'Second Co')->first();
        $subs = app(Subscriptions::class);
        \App\Services\Billing\PlatformSettings::set(['free_limit' => 0]); // bill even a one-person company here
        $subs->startPostpaid($company);
        $company->refresh();
        $this->assertSame('postpaid', $company->billing_type);

        // A month later the daily run invoices actual headcount
        $company->forceFill(['billing_anchor' => now()->subMonth()->subDay()->toDateString(), 'last_billed_at' => null])->save();
        $stats = $subs->runDaily();
        $this->assertSame(1, Invoice::withoutGlobalScopes()->where('company_id', $company->id)->where('kind', 'usage')->count());
        $inv = Invoice::where('company_id', $company->id)->where('kind', 'usage')->first();
        $this->assertEquals(500, (float) $inv->total); // minimum charge (1 employee)
        Mail::assertSent(\App\Mail\BillingMail::class, fn ($m) => $m->kind === 'invoice');

        // Overdue past grace → limited
        $inv->forceFill(['due_at' => now()->subDays(10)->toDateString()])->save();
        $subs->runDaily();
        $this->assertSame('suspended', $company->fresh()->subscription_status);
        $this->assertTrue($subs->isLimited($company->fresh()));
        $inv->forceFill(['status' => 'void'])->save();
        $company->forceFill(['subscription_status' => 'free', 'billing_type' => null, 'plan' => null, 'trial_ends_at' => null])->save();
        \App\Services\Billing\PlatformSettings::set(['free_limit' => 10]);
    }
}
