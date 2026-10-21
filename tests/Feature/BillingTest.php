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

    public function test_trial_prepaid_payment_and_limits(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $company = Company::find($admin->company_id);
        $this->assertSame('trialing', $company->subscription_status);
        $this->assertNotNull($company->trial_ends_at);

        $this->actingAs($admin)->get('http://localhost/employees')->assertOk()->assertSee('Free trial')->assertSee('left in your free trial');
        $this->get('http://localhost/settings/billing')->assertOk()->assertSee('Choose your plan')->assertSee('Starter');

        // Trial over → read-only
        $company->forceFill(['trial_ends_at' => now()->subDay()])->save();
        $this->post('http://localhost/employees', ['first_name' => 'X', 'last_name' => 'Y'])->assertRedirect(route('billing.index'));
        $this->get('http://localhost/employees')->assertOk(); // viewing still works

        // Choose Starter monthly → invoice → pay by mobile money (DPO fake mode)
        $this->post('http://localhost/settings/billing/prepaid', ['cycle' => 'monthly'])->assertRedirect();
        $invoice = Invoice::where('company_id', $company->id)->firstOrFail();
        $this->assertSame('open', $invoice->status);
        $this->assertEquals(350, (float) $invoice->total);

        $r = $this->postJson('http://localhost/settings/billing/invoices/' . $invoice->id . '/mobile', ['network' => 'airtel', 'phone' => '0977123456'])->assertOk()->json();
        $this->getJson($r['status_url'])->assertOk()->assertJson(['status' => 'paid']);
        $this->assertSame('paid', $invoice->fresh()->status);
        $company->refresh();
        $this->assertSame(['active', 'prepaid', 'starter'], [$company->subscription_status, $company->billing_type, $company->plan]);
        $this->assertTrue($company->paid_until->isFuture());
        Mail::assertSent(\App\Mail\BillingMail::class, fn ($m) => $m->kind === 'receipt');

        // Unlocked again; can't pay twice
        $this->post('http://localhost/employees', ['first_name' => 'Paid', 'last_name' => 'Up'])->assertRedirect();
        $this->postJson('http://localhost/settings/billing/invoices/' . $invoice->id . '/card')->assertStatus(422);
        $this->get('http://localhost/settings/billing/invoices/' . $invoice->id . '/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');

        // Repeated DPO notifications don't double-extend
        $until = $company->paid_until->copy();
        $p = BillingPayment::where('invoice_id', $invoice->id)->first();
        $this->post('http://localhost/billing/dpo/notify', ['TransactionToken' => $p->trans_token])->assertOk();
        $this->assertTrue($company->fresh()->paid_until->eq($until));

        // Another company can't see the invoice
        $this->actingAs(User::where('email', 'admin@second.com')->first())->get('http://localhost/settings/billing/invoices/' . $invoice->id . '/pdf')->assertNotFound();
        \App\Models\Employee::where('first_name', 'Paid')->delete();
    }

    public function test_postpaid_invoices_and_overdue_limit(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $company = Company::where('name', 'Second Co')->first();
        $subs = app(Subscriptions::class);
        $subs->startPostpaid($company);
        $company->refresh();
        $this->assertSame('postpaid', $company->billing_type);

        // A month later the daily run invoices actual headcount
        $company->forceFill(['billing_anchor' => now()->subMonth()->subDay()->toDateString(), 'last_billed_at' => null])->save();
        $stats = $subs->runDaily();
        $this->assertSame(1, Invoice::where('company_id', $company->id)->where('kind', 'usage')->count());
        $inv = Invoice::where('company_id', $company->id)->where('kind', 'usage')->first();
        $this->assertEquals(500, (float) $inv->total); // minimum charge (1 employee)
        Mail::assertSent(\App\Mail\BillingMail::class, fn ($m) => $m->kind === 'invoice');

        // Overdue past grace → limited
        $inv->forceFill(['due_at' => now()->subDays(10)->toDateString()])->save();
        $subs->runDaily();
        $this->assertSame('suspended', $company->fresh()->subscription_status);
        $this->assertTrue($subs->isLimited($company->fresh()));
        $inv->forceFill(['status' => 'void'])->save();
        $company->forceFill(['subscription_status' => 'trialing', 'billing_type' => null, 'plan' => null, 'trial_ends_at' => now()->addDays(7)])->save();
    }
}
