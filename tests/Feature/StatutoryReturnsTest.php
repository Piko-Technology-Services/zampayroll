<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\StatutoryReturn;
use App\Models\User;
use App\Services\HrActions;
use App\Services\Statutory\StatutoryReturns;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** NAPSA / NHIMA / ZRA returns use the right payroll lines, add up multiple runs, and can be corrected before export. */
class StatutoryReturnsTest extends TestCase
{
    private User $admin;
    private array $runs = [];
    private $johnNet = null;
    private array $maryWas = [];

    protected function setUp(): void
    {
        parent::setUp();
        Tenancy::reset();
        DB::setDefaultConnection('mysql');
        $this->admin = User::where('email', 'admin@pikozm.com')->first();
        Tenancy::setCompanyId($this->admin->company_id);
        $this->maryWas = Employee::where('employee_id', 'EMP-001')->first()->only(['net_salary', 'tpin', 'ssn', 'nrc_no', 'date_of_birth']);
        Employee::where('employee_id', 'EMP-001')->update(['net_salary' => 6500, 'tpin' => '1000000001', 'ssn' => '123456789012', 'nrc_no' => '111111/11/1', 'date_of_birth' => '1990-05-01']);
        $this->johnNet = Employee::where('employee_id', 'EMP-002')->value('net_salary');
        Employee::where('employee_id', 'EMP-002')->update(['net_salary' => 5200]);
        PayrollRun::withTrashed()->where('period', '2026-02')->get()->each(fn ($r) => $r->forceDelete());
        foreach (['First', 'Second'] as $i => $alias) {
            $run = PayrollRun::create(['period' => '2026-02', 'alias' => "February 2026 {$alias}", 'created_by' => $this->admin->id, 'status' => PayrollRun::DRAFT]);
            app(HrActions::class)->generatePayroll($run, $this->admin);
            $run->update(['status' => PayrollRun::APPROVED]);
            $this->runs[] = $run;
        }
    }

    protected function tearDown(): void
    {
        StatutoryReturn::query()->where('period', '2026-02')->delete();
        Employee::where('employee_id', 'EMP-002')->update(['net_salary' => $this->johnNet]);
        Employee::where('employee_id', 'EMP-001')->update($this->maryWas);
        foreach ($this->runs as $run) {
            $run->payrolls()->each(fn ($p) => $p->items()->delete());
            $run->payrolls()->delete();
            $run->forceDelete();
        }
        parent::tearDown();
    }

    private function line(array $rows, string $label): array
    {
        return collect($rows)->firstWhere('label', $label);
    }

    public function test_values_come_from_the_right_payroll_lines_and_months_add_up(): void
    {
        $svc = app(StatutoryReturns::class);
        $run = $this->runs[0];
        $p = $run->payrolls()->with('items', 'employee')->get()->firstWhere('employee.employee_id', 'EMP-001');
        $napsa = (float) $p->items->firstWhere('code', 'D01')->amount;
        $nhima = (float) $p->items->firstWhere('code', 'D02')->amount;
        $paye = (float) $p->items->firstWhere('code', 'D00')->amount;
        $this->assertNotEquals($napsa, $nhima);

        $one = $this->line($svc->generate('napsa', collect([$run])), 'Mary Banda');
        $this->assertEquals($napsa, $one['cells']['employee'], 'NAPSA uses D01');
        $this->assertEquals($napsa, $one['cells']['company']);
        $this->assertSame(2026, $one['cells']['year']);
        $this->assertSame(2, $one['cells']['month']);
        $this->assertSame('123456789012', $one['cells']['ssn']);
        $this->assertEmpty(collect($one['checks'])->where('level', 'fix'));

        $this->assertEquals($nhima, $this->line($svc->generate('nhima', collect([$run])), 'Mary Banda')['cells']['employee'], 'NHIMA uses D02');
        $zra = $this->line($svc->generate('zra', collect([$run])), 'Mary Banda');
        $this->assertEquals($paye, $zra['cells']['tax'], 'ZRA uses D00');
        $this->assertEmpty($zra['checks'], 'PAYE matches the tax bands for one run');

        // Whole month: two runs added up per employee, and the PAYE check warns about separate taxing
        $month = $this->line($svc->current('napsa', $run, 'month')['rows']->all(), 'Mary Banda');
        $this->assertEquals(round($napsa * 2, 2), $month['cells']['employee']);
        $this->assertSame(2, $month['runs']);
        $zraMonth = $this->line($svc->current('zra', $run, 'month')['rows']->all(), 'Mary Banda');
        $this->assertEquals(round($paye * 2, 2), $zraMonth['cells']['tax']);
        $this->assertNotEmpty($zraMonth['checks']);
    }

    public function test_review_edit_save_download_and_reset(): void
    {
        $run = $this->runs[0];
        $base = 'http://localhost/payroll/reports/' . $run->id . '/returns/';
        $this->actingAs($this->admin)->get($base . 'napsa?scope=month')->assertOk()->assertSee('NAPSA — iCARE returns')->assertSee('Whole month (2 pay runs)')->assertSee('Mary Banda');

        $rows = app(StatutoryReturns::class)->current('napsa', $run, 'month')['rows'];
        $mary = $rows->firstWhere("label", "Mary Banda");
        $mary['cells']['ssn'] = '999999999999';
        $mary['cells']['employee'] = '123.45';
        $others = $rows->reject(fn ($r) => $r['label'] === 'Mary Banda')->values();
        $removedKey = $others->first()['key'];
        $payload = $others->slice(1)->push($mary)->push(['key' => 'new1', 'label' => 'Casual worker', 'cells' => ['employer' => 'X', 'year' => 2026, 'month' => 2, 'ssn' => '555', 'last' => 'Worker', 'first' => 'Casual', 'gross' => '1000', 'employee' => '50', 'company' => '50', 'flag' => 'I']])
            ->map(fn ($r) => ['key' => $r['key'], 'label' => $r['label'], 'employee_id' => $r['employee_id'] ?? null, 'cells' => $r['cells']])->values()->all();

        $this->postJson($base . 'napsa', ['rows' => $payload, 'removed' => [$removedKey], 'scope' => 'month'])->assertOk()->assertJson(['ok' => true]);
        $this->get($base . 'napsa?scope=month')->assertOk()->assertSee('corrected version')->assertSee('999999999999')->assertSee('Casual worker');

        $csv = $this->get($base . 'napsa/download?scope=month')->assertOk()->streamedContent();
        $this->assertStringContainsString('"company napsa number",year,month,ssn', $csv, 'NAPSA file keeps its header row');
        $this->assertStringContainsString('999999999999', $csv);
        $this->assertStringContainsString('123.45', $csv);
        $this->assertStringContainsString('Casual', $csv);
        $this->assertSame(count($payload) + 1, substr_count(trim($csv), "\n") + 1, 'header + edited lines, removed line left out');

        // NHIMA file has no header by default (matches the portal's template)
        $nh = $this->get($base . 'nhima/download?scope=run')->assertOk()->streamedContent();
        $this->assertStringNotContainsString('nhima employer number', $nh);
        $this->assertStringContainsString('nhima employer number', $this->get($base . 'nhima/download?scope=run&with_header=1')->streamedContent());

        // ZRA is an Excel file
        $this->get($base . 'zra/download?scope=run')->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Reset → payroll figures again
        $this->post($base . 'napsa/reset?scope=month')->assertRedirect();
        $this->assertStringNotContainsString('999999999999', $this->get($base . 'napsa/download?scope=month')->streamedContent());

        // Old direct download links still work
        $this->get('http://localhost/payroll/reports/' . $run->id . '/napsa/csv')->assertOk();
    }
}
