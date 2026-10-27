<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** People directory (cards, filters) and departments (overview, create, rename, remove). */
class PeopleDirectoryTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Tenancy::reset();
        DB::setDefaultConnection('mysql');
        $this->admin = User::where('email', 'admin@pikozm.com')->first();
        Tenancy::setCompanyId($this->admin->company_id);
    }

    public function test_directory_shows_cards_filters_and_department_chips(): void
    {
        $mary = Employee::where('employee_id', 'EMP-001')->first();
        $mary->update(['department' => 'Finance']);

        $this->actingAs($this->admin)->get('http://localhost/employees')->assertOk()
            ->assertSee('People')->assertSee('em-card', false)->assertSee($mary->first_name)
            ->assertSee('data-dept-chip="Finance"', false)->assertSee('Search by name, job, number, phone or email');
        $this->get('http://localhost/employees?department=Finance')->assertOk()->assertSee('&quot;department&quot;:&quot;Finance&quot;', false);
    }

    public function test_departments_overview_create_rename_and_remove(): void
    {
        $this->actingAs($this->admin)->get('http://localhost/departments')->assertOk()->assertSee('Departments')->assertSee('New department');

        $this->post('http://localhost/departments', ['name' => '  Warehouse  ', 'description' => 'Trucks and stores', 'color' => '#2C6E9B'])->assertSessionHas('success');
        $dept = Department::where('name', 'Warehouse')->firstOrFail();
        $this->assertSame((int) $this->admin->company_id, (int) $dept->company_id);
        $this->post('http://localhost/departments', ['name' => 'warehouse'])->assertSessionHasErrors('name');

        // An employee joins Warehouse; renaming the department renames it on the employee
        $e = Employee::where('employee_id', 'EMP-002')->first();
        $e->update(['department' => 'Warehouse']);
        $this->put('http://localhost/departments/' . $dept->id, ['name' => 'Warehouse & Stores', 'color' => '#2C6E9B'])->assertSessionHas('success');
        $this->assertSame('Warehouse & Stores', $e->fresh()->department);
        $this->get('http://localhost/departments')->assertSee('Warehouse &amp; Stores', false)->assertSee('Trucks and stores');

        // Removing it moves people to another department
        $this->delete('http://localhost/departments/' . $dept->id, ['move_to' => 'Operations'])->assertSessionHas('success');
        $this->assertSame('Operations', $e->fresh()->department);
        $this->assertNull(Department::find($dept->id));

        // Read-only roles can see departments but not change them
        $viewer = User::firstOrCreate(['email' => 'viewer.depts@pikozm.com'], ['company_id' => $this->admin->company_id, 'name' => 'View Only',
            'password' => bcrypt('x12345678'), 'role' => 'viewer', 'email_verified_at' => now()]);
        $this->actingAs($viewer)->get('http://localhost/departments')->assertOk()->assertDontSee('data-dp-open="new"', false);
        $this->post('http://localhost/departments', ['name' => 'Sneaky'])->assertForbidden();
    }

    public function test_new_department_typed_on_employee_form_is_added_to_the_list(): void
    {
        $this->actingAs($this->admin)->get('http://localhost/employees/create')->assertOk()->assertSee('+ Add a new department…');
        $this->post('http://localhost/employees', ['first_name' => 'Dept', 'last_name' => 'Newcomer', 'department' => 'Quality Control', 'net_salary' => 5000])->assertRedirect();
        $this->assertTrue(Department::where('name', 'Quality Control')->exists());
        Employee::where('last_name', 'Newcomer')->delete();
        Department::where('name', 'Quality Control')->delete();
    }
}
