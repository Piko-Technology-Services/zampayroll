<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RolesSiteTest extends TestCase
{
    private function admin(): User
    {
        Tenancy::reset(); DB::setDefaultConnection('mysql');
        return User::where('email', 'admin@pikozm.com')->first();
    }

    public function test_company_code_login(): void
    {
        $admin = $this->admin();
        $code = $admin->company->company_code;
        $this->assertMatchesRegularExpression('/^[A-Z]{3}-\d{4}$/', $code);

        // No company code needed; a stray or other company's code is simply ignored
        $other = Company::where('name', 'Second Co')->value('company_code');
        $this->post('http://localhost/login', ['company_code' => $other, 'email' => 'admin@pikozm.com', 'password' => 'wrong-pass'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('http://localhost/login', ['email' => 'admin@pikozm.com', 'password' => 'password123'])
            ->assertRedirect(route('dashboard'))->assertCookie('zp_company_code');
        $this->assertAuthenticatedAs($admin);
        $this->get('http://localhost/company-code')->assertRedirect(); // guest-only page
    }

    public function test_roles_restrict_access_and_admin_can_edit(): void
    {
        $admin = $this->admin();
        $manager = User::create(['company_id' => $admin->company_id, 'name' => 'Mo Manager', 'email' => 'mo@pikozm.com', 'password' => bcrypt('x12345678'), 'role' => 'manager', 'email_verified_at' => now()]);

        // manager: can approve leave, cannot see payroll, settings or team
        $this->actingAs($manager)->get('http://localhost/leave/dashboard/requests')->assertOk();
        $this->actingAs($manager)->get('http://localhost/payroll/runs/all')->assertForbidden()->assertSee('part of your role');
        $this->actingAs($manager)->get('http://localhost/settings/team')->assertForbidden();
        $this->actingAs($manager)->get('http://localhost/settings/company')->assertForbidden();
        $this->actingAs($manager)->get('http://localhost/dashboard')->assertOk()->assertDontSee('Pay, overtime, loans, returns');
        $this->actingAs($manager)->postJson('http://localhost/ai/message', ['message' => "prepare this month's payroll"])
            ->assertOk()->assertSee('Your role doesn');

        // admin gives managers payroll.view via the matrix
        $this->actingAs($admin)->get('http://localhost/settings/team')->assertOk()->assertSee($admin->company->company_code)->assertSee('Mo Manager');
        $perms = CompanyRole::withoutGlobalScopes()->where('company_id', $admin->company_id)->where('key', 'manager')->first()->permissions;
        $this->actingAs($admin)->put('http://localhost/settings/team/roles', ['permissions' => ['manager' => array_merge($perms, ['payroll.view'])]])->assertSessionHas('success');
        Tenancy::reset();
        $this->actingAs(User::find($manager->id))->get('http://localhost/payroll/runs/all')->assertOk();

        // change a member's role; custom role
        $this->actingAs($admin)->put('http://localhost/settings/team/members/' . $manager->id, ['role' => 'viewer'])->assertSessionHas('success');
        $this->assertSame('viewer', $manager->fresh()->role);
        $this->actingAs($admin)->post('http://localhost/settings/team/roles', ['name' => 'Branch Supervisor', 'copy_from' => 'manager'])->assertRedirect();
        $this->assertTrue(CompanyRole::withoutGlobalScopes()->where('company_id', $admin->company_id)->where('key', 'branch_supervisor')->exists());

        // last admin can't demote themselves
        $this->actingAs($admin)->put('http://localhost/settings/team/members/' . $admin->id, ['role' => 'viewer'])->assertSessionHas('error');

        // roles are per company
        $otherAdmin = User::where('email', 'admin@second.com')->first();
        $this->actingAs($otherAdmin)->put('http://localhost/settings/team/members/' . $manager->id, ['role' => 'hr'])->assertNotFound();
        $manager->delete();
    }

    public function test_public_site_and_seo(): void
    {
        $this->get('http://localhost/')->assertOk()
            ->assertSee('Zambian payroll, done in minutes', false)
            ->assertSee('application/ld+json', false)->assertSee('FAQPage')->assertSee('og:image', false)
            ->assertSee('wa.me/260776136965', false)->assertSee('data-zp-lang', false)
            ->assertSee('<link rel="canonical" href="https://zampayroll.com/">', false);
        foreach (['/features', '/ai-assistant', '/pricing', '/terms', '/privacy'] as $p) {
            $this->get('http://localhost' . $p)->assertOk()->assertSee('rel="canonical"', false);
        }
        $this->get('http://localhost/sitemap.xml')->assertOk()->assertHeader('content-type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>https://zampayroll.com/pricing</loc>', false);
        $this->get('http://localhost/login')->assertOk()->assertSee('Email address')->assertSee('zh-CN', false);
        $this->post('http://localhost/demo-request', ['full_name' => 'A', 'email' => 'a@b.zm', 'company_name' => 'C'])->assertRedirect('http://localhost#demo');
    }
}
