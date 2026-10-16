<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AiModeTest extends TestCase
{
    private function admin(): User
    {
        Tenancy::reset(); DB::setDefaultConnection('mysql');
        return User::where('email', 'admin@pikozm.com')->first();
    }

    private function ask(string $msg, ?UploadedFile $file = null)
    {
        $data = ['message' => $msg];
        if ($file) $data['attachment'] = $file;
        return $this->actingAs($this->admin())->post('http://localhost/ai/message', $data, ['Accept' => 'application/json']);
    }

    public function test_basic_mode_questions(): void
    {
        config(['ai.api_key' => null]);
        $this->ask('How many employees do we have?')->assertOk()->assertJson(['mode' => 'basic'])
            ->assertJsonPath('cards.0.type', 'stats')->assertSee('2 active employees');
        $this->ask('Who is on leave today?')->assertOk()->assertJsonStructure(['reply']);
        $this->ask('How much was our payroll?')->assertOk()->assertSee('6,500.00');
        $this->ask('what is pending?')->assertOk()->assertSee('Waiting for you');
    }

    public function test_basic_mode_actions_need_confirmation(): void
    {
        Mail::fake();
        config(['ai.api_key' => null]);
        $mary = Employee::withoutGlobalScopes()->where('first_name', 'Mary')->first();
        $leave = LeaveRequest::withoutGlobalScopes()->create(['company_id' => $mary->company_id, 'employee_id' => $mary->id, 'leave_type' => 'annual', 'company_email' => 'mary@pikozm.com', 'start_date' => '2026-12-01', 'end_date' => '2026-12-03', 'days' => 3, 'status' => 'pending']);

        $res = $this->ask('approve leave for Mary')->assertOk()->json();
        $card = collect($res['cards'])->firstWhere('type', 'action');
        $this->assertNotNull($card, json_encode($res));
        $this->assertSame('pending', $leave->fresh()->status, 'nothing changes before confirm');

        $this->actingAs($this->admin())->postJson('http://localhost/ai/actions/' . $card['token'] . '/confirm')->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('approved', $leave->fresh()->status);
        // token is single-use
        $this->actingAs($this->admin())->postJson('http://localhost/ai/actions/' . $card['token'] . '/confirm')->assertStatus(410);

        // prepare payroll
        $res = $this->ask("prepare this month's payroll")->json();
        $card = collect($res['cards'])->firstWhere('type', 'action');
        $this->actingAs($this->admin())->postJson('http://localhost/ai/actions/' . $card['token'] . '/confirm')->assertOk()->assertJson(['ok' => true]);
        $this->assertTrue(PayrollRun::withoutGlobalScopes()->where('company_id', $mary->company_id)->where('period', now()->format('Y-m'))->exists());
        $this->assertFalse(PayrollRun::withoutGlobalScopes()->where('company_id', '!=', $mary->company_id)->where('period', now()->format('Y-m'))->exists());
    }

    public function test_basic_mode_imports_employees_from_file(): void
    {
        config(['ai.api_key' => null]);
        $csv = "Full Name,Job Title,Dept,Mobile,NRC No,NAPSA No,Net Salary,Email\n"
             . "Chilufya Mwansa,Accountant,Finance,0977111222,123456/10/1,NAP123,\"9,500\",chilufya@pikozm.com\n"
             . "Mutale Zulu,Driver,Logistics,0966333444,654321/11/1,,4200,\n"
             . "Mary Banda,Officer,Finance,,,,,mary@pikozm.com\n";
        $file = UploadedFile::fake()->createWithContent('staff.csv', $csv);

        $res = $this->ask('', $file)->assertOk()->json();
        $card = collect($res['cards'])->firstWhere('type', 'action');
        $this->assertSame('Add 2 employees', $card['title'], json_encode($res));

        $this->actingAs($this->admin())->postJson('http://localhost/ai/actions/' . $card['token'] . '/confirm')->assertOk();
        $c = Employee::withoutGlobalScopes()->where('first_name', 'Chilufya')->first();
        $this->assertEquals(9500, (float) $c->net_salary);
        $this->assertSame('NAP123', $c->ssn);
        $this->assertSame($this->admin()->company_id, $c->company_id);
    }

    public function test_claude_tool_loop(): void
    {
        config(['ai.api_key' => 'test-key', 'ai.provider' => 'anthropic']);
        Http::fakeSequence('api.anthropic.com/*')
            ->push(['stop_reason' => 'tool_use', 'content' => [
                ['type' => 'text', 'text' => 'Let me check.'],
                ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'company_overview', 'input' => []],
            ]])
            ->push(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => 'You have **2** active employees.']]]);

        $this->ask('How many people work here?')->assertOk()
            ->assertJson(['mode' => 'ai', 'reply' => 'You have **2** active employees.'])
            ->assertJsonPath('cards.0.type', 'stats');

        Http::assertSent(function ($req) {
            return $req->hasHeader('x-api-key', 'test-key') && count($req['tools']) >= 10 && str_contains($req['system'], 'Pikozm Ltd');
        });
    }

    public function test_no_external_calls_by_default(): void
    {
        config(['ai.api_key' => 'some-key']); // even with a key present, default provider is basic
        Http::fake();
        $this->ask('How many employees do we have?')->assertOk()->assertJson(['mode' => 'basic']);
        Http::assertNothingSent();
    }

    public function test_employees_cannot_use_ai_and_ui_renders(): void
    {
        $this->actingAs($this->admin())->get('http://localhost/dashboard')->assertOk()
            ->assertSee('Ask ZamPayroll AI')->assertDontSee('Public Forms');
        $this->actingAs($this->admin())->get('http://localhost/employees')->assertOk()->assertSee('zp-tabs', false)->assertSee('Add with AI');
        $this->actingAs($this->admin())->get('http://localhost/leave/dashboard/requests')->assertOk()->assertSee('Leave sheet');
        $this->actingAs($this->admin())->get('http://localhost/payroll/runs/all')->assertOk()->assertSee('Reports &amp; returns', false);
    }
}
