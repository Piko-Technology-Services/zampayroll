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
