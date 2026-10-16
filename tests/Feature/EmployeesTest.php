<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeColumnMapper;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeesTest extends TestCase
{
    private function admin(): User
    {
        Tenancy::reset(); DB::setDefaultConnection('mysql');
        return User::where('email', 'admin@pikozm.com')->first();
    }

    public function test_column_mapper_understands_other_payroll_exports(): void
    {
        $map = EmployeeColumnMapper::guessAll(['Man No', 'Surname', 'First Name', 'NRC No.', 'NAPSA No', 'Take Home', 'Bank A/C No', 'Dept', 'Mystery']);
        $this->assertSame(['Man No' => 'employee_id', 'Surname' => 'last_name', 'First Name' => 'first_name', 'NRC No.' => 'nrc_no',
            'NAPSA No' => 'ssn', 'Take Home' => 'net_salary', 'Bank A/C No' => 'bank_account_no', 'Dept' => 'department', 'Mystery' => null], $map);
        $hr = app(\App\Services\HrActions::class);
        $this->assertSame('1990-03-15', $hr->parseDate('15/03/1990'));
        $this->assertSame('1990-03-15', $hr->parseDate('32947'));
        $this->assertSame('1985-12-01', $hr->parseDate('01-12-85'));
    }

    public function test_bulk_import_wizard_parse_preview_commit(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('http://localhost/employees/import')->assertOk()->assertSee('Import your whole team');
        $this->actingAs($admin)->get('http://localhost/employees/import/template')->assertOk();

        $existing = Employee::first();
        $paste = "Full Name\tNRC\tJob Title\tNet Pay\tEmail\tDate Joined\n"
            . "Bwalya Kunda Phiri\t111111/11/1\tDriver\tK 4,500.00\tbwalya@example.com\t01/02/2024\n"
            . "Natasha Zulu\t222222/22/1\tCashier\t5200\tnot-an-email\t2024-03-01\n"
            . "{$existing->first_name} {$existing->last_name}\t\tClerk\t3000\t\t\n"
            . "\t\tNobody\t100\t\t\n";

        $parsed = $this->actingAs($admin)->postJson('http://localhost/employees/import/parse', ['paste' => $paste])->assertOk()->json();
        $this->assertCount(4, $parsed['rows']);
        $this->assertSame('name', $parsed['mapping']['Full Name']);
        $this->assertSame('contract_start', $parsed['mapping']['Date Joined']);

        $rows = EmployeeColumnMapper::apply($parsed['rows'], $parsed['mapping']);
        $preview = $this->actingAs($admin)->postJson('http://localhost/employees/import/preview', ['rows' => $rows])->assertOk()->json();
        $this->assertSame(['ready', 'warning', 'duplicate', 'error'], array_column($preview['rows'], 'status'));

        $before = Employee::count();
        $res = $this->actingAs($admin)->postJson('http://localhost/employees/import/commit', ['rows' => array_slice($rows, 0, 3), 'duplicates' => 'skip'])->assertOk()->json();
        $this->assertSame([2, 0, 1], [$res['created'], $res['updated'], $res['skipped']]);
        $this->assertSame($before + 2, Employee::count());
        $b = Employee::where('nrc_no', '111111/11/1')->first();
        $this->assertSame(['Bwalya', 'Kunda', 'Phiri', '4500.00', '2024-02-01'], [$b->first_name, $b->middle_name, $b->last_name, number_format((float) $b->net_salary, 2, '.', ''), $b->contract_start->toDateString()]);
        $this->assertSame($admin->company_id, $b->company_id);

        // update mode changes the existing person
        $res = $this->actingAs($admin)->postJson('http://localhost/employees/import/commit', ['rows' => [$rows[2]], 'duplicates' => 'update'])->assertOk()->json();
        $this->assertSame(1, $res['updated']);
        $this->assertSame('Clerk', $existing->fresh()->position);

        // Excel upload works too
        $csv = UploadedFile::fake()->createWithContent('team.csv', "First name,Surname,Mobile\nMutale,Banda,0977000000\n");
        $p = $this->actingAs($admin)->post('http://localhost/employees/import/parse', ['file' => $csv], ['Accept' => 'application/json'])->assertOk()->json();
        $this->assertSame(['First name' => 'first_name', 'Surname' => 'last_name', 'Mobile' => 'primary_phone'], $p['mapping']);

        // other company never sees these people
        Employee::whereIn('nrc_no', ['111111/11/1', '222222/22/1'])->delete();
    }

    public function test_add_journey_saves_with_only_names_and_full_profile(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('http://localhost/employees/create')->assertOk()->assertSee('Let&#039;s welcome someone new', false)->assertSee('Save &amp; finish later', false);

        $this->actingAs($admin)->post('http://localhost/employees', ['first_name' => '', 'last_name' => 'X'])->assertSessionHasErrors('first_name');

        $r = $this->actingAs($admin)->post('http://localhost/employees', ['first_name' => 'Chileshe', 'last_name' => 'Mumba', 'intent' => 'later']);
        $e = Employee::where('first_name', 'Chileshe')->latest('id')->first();
        $r->assertRedirect(route('employees.show', $e))->assertSessionHas('success');
        $this->assertSame(['Active', 'PERMANENT', 'Zambian'], [$e->employment_status, $e->employment_nature, $e->nationality]);
        $this->assertStringStartsWith('EMP-', $e->employee_id);

        $page = $this->actingAs($admin)->get('http://localhost/employees/' . $e->id)->assertOk()->assertSee('Chileshe Mumba')->assertSee('Still missing')->assertSee('NRC');

        // edit journey, open on the pay step
        $this->actingAs($admin)->get('http://localhost/employees/' . $e->id . '/edit?step=pay')->assertOk()->assertSee('data-start="3"', false);
        $this->actingAs($admin)->put('http://localhost/employees/' . $e->id, [
            'first_name' => 'Chileshe', 'last_name' => 'Mumba', 'employee_id' => 'ZP-0099', 'gender' => 'Female', 'date_of_birth' => '1994-05-02',
            'nrc_no' => '333333/33/1', 'position' => 'Engineer', 'department' => 'Ops', 'contract_start' => '2025-01-06',
            'net_salary' => '9100', 'bank_name' => 'Zanaco', 'bank_account_no' => '0011', 'tpin' => '1000000001', 'ssn' => 'N1', 'nhima_no' => 'H1',
            'primary_phone' => '0977111111', 'company_email' => 'chileshe@example.com', 'next_of_kin_name' => 'Joy', 'employment_nature' => 'CONTRACT',
        ])->assertRedirect(route('employees.show', $e));
        $e->refresh();
        $this->assertSame(['ZP-0099', 'CONTRACT', 100], [$e->employee_id, $e->employment_nature, $e->profileCompleteness()['percent']]);
        $this->actingAs($admin)->get('http://localhost/employees/' . $e->id)->assertSee('Profile complete')->assertSee('K 9,100.00');

        // a viewer-style role (manager) doesn't see pay
        $manager = User::create(['company_id' => $admin->company_id, 'name' => 'Mo', 'email' => 'mo2@pikozm.com', 'password' => bcrypt('x12345678'), 'role' => 'manager', 'email_verified_at' => now()]);
        $this->actingAs($manager)->get('http://localhost/employees/' . $e->id)->assertOk()->assertDontSee('K 9,100.00')->assertSee('Hidden for your role');
        $this->actingAs($manager)->get('http://localhost/employees/create')->assertForbidden();
        $this->actingAs($manager)->get('http://localhost/employees/import')->assertForbidden();
        $manager->delete();
        $e->delete();
    }

    public function test_employee_list_renders(): void
    {
        $this->actingAs($this->admin())->get('http://localhost/employees')->assertOk()->assertSee('Import a list');
    }
}
