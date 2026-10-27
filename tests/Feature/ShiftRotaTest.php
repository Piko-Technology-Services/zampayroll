<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Admin defines shifts, HR schedules anyone, a supervisor schedules only their own people, employees see their shifts. */
class ShiftRotaTest extends TestCase
{
    private User $admin;
    private Employee $mary;
    private Employee $john;
    private array $was = [];
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        Tenancy::reset();
        DB::setDefaultConnection('mysql');
        $this->admin = User::where('email', 'admin@pikozm.com')->first();
        Tenancy::setCompanyId($this->admin->company_id);
        $this->mary = Employee::where('employee_id', 'EMP-001')->first();
        $this->john = Employee::where('employee_id', 'EMP-002')->first();
        $this->was = [$this->mary->only(['department', 'supervisor']), $this->john->only(['department', 'supervisor'])];
        $this->mary->forceFill(['department' => 'Finance', 'supervisor' => null])->save();
        $this->john->forceFill(['department' => 'Operations', 'supervisor' => null])->save();
        ShiftAssignment::query()->delete();
        Shift::query()->delete();
    }

    protected function tearDown(): void
    {
        ShiftAssignment::query()->delete();
        Shift::query()->delete();
        foreach ($this->users as $u) {
            $u->delete();
        }
        $this->mary->forceFill($this->was[0])->save();
        $this->john->forceFill($this->was[1])->save();
        parent::tearDown();
    }

    private function user(string $email, string $role, ?int $employeeId): User
    {
        return $this->users[] = User::create(['company_id' => $this->admin->company_id, 'name' => $role === 'manager' ? 'John Phiri' : 'Mary Banda', 'email' => $email,
            'password' => bcrypt('x12345678'), 'role' => $role, 'employee_id' => $employeeId, 'email_verified_at' => now()]);
    }

    public function test_define_shifts_schedule_copy_and_scope(): void
    {
        $this->actingAs($this->admin);
        $this->get('http://localhost/attendance/rota')->assertOk()->assertSee('No shifts set up yet');
        $this->get('http://localhost/attendance/shifts')->assertOk()->assertSee('Company shifts');

        $this->post('http://localhost/attendance/shifts', ['name' => 'Morning', 'start_time' => '06:00', 'end_time' => '14:00', 'break_minutes' => 30, 'color' => '#00742D'])->assertSessionHas('success');
        $this->post('http://localhost/attendance/shifts', ['name' => 'Night', 'code' => 'n', 'start_time' => '22:00', 'end_time' => '06:00'])->assertSessionHas('success');
        $this->post('http://localhost/attendance/shifts', ['name' => 'Morning', 'start_time' => '07:00', 'end_time' => '15:00'])->assertSessionHasErrors('name');
        $this->post('http://localhost/attendance/shifts', ['name' => 'Odd', 'start_time' => '07:00', 'end_time' => '07:00'])->assertSessionHasErrors('end_time');

        $morning = Shift::where('name', 'Morning')->first();
        $night = Shift::where('name', 'Night')->first();
        $this->assertSame('M', $morning->code);
        $this->assertSame('N', $night->code);
        $this->assertSame(7.5, $morning->hours());
        $this->assertSame(8.0, $night->hours());
        $this->assertTrue($night->isOvernight());
        $this->put('http://localhost/attendance/shifts/' . $morning->id, ['name' => 'Early', 'start_time' => '06:00', 'end_time' => '14:00', 'break_minutes' => 30, 'active' => 1])->assertSessionHas('success');
        $this->assertSame('Early', $morning->fresh()->name);

        // HR/admin schedules anyone
        $mon = Carbon::parse('2026-11-02');
        $d = fn ($i) => $mon->copy()->addDays($i)->toDateString();
        $this->postJson('http://localhost/attendance/rota', ['cells' => [
            ['employee_id' => $this->mary->id, 'date' => $d(0), 'value' => $morning->id],
            ['employee_id' => $this->mary->id, 'date' => $d(1), 'value' => 'off'],
            ['employee_id' => $this->john->id, 'date' => $d(0), 'value' => $night->id],
            ['employee_id' => $this->john->id, 'date' => $d(2), 'value' => $night->id],
        ]])->assertOk()->assertJson(['ok' => true, 'saved' => 4]);
        $this->assertSame(4, ShiftAssignment::count());
        $this->assertNull(ShiftAssignment::where('employee_id', $this->mary->id)->whereDate('date', $d(1))->value('shift_id'), 'day off');
        $this->postJson('http://localhost/attendance/rota', ['cells' => [['employee_id' => $this->john->id, 'date' => $d(2), 'value' => null]]])->assertOk();
        $this->assertSame(3, ShiftAssignment::count(), 'clearing removes the day');
        $this->postJson('http://localhost/attendance/rota', ['cells' => [['employee_id' => $this->john->id, 'date' => $d(3), 'value' => 99999]]])->assertStatus(422);

        $this->get('http://localhost/attendance/rota?week=' . $d(3))->assertOk()->assertSee('Shift rota')->assertSee('Mary Banda')->assertSee('John Phiri')->assertSee('2 Nov – 8 Nov 2026')
            ->assertSee('Early')->assertSee('06:00–14:00');

        // Repeat for 2 weeks, then copy "last week" fills only empty days
        $this->post('http://localhost/attendance/rota/copy', ['week' => $d(0), 'mode' => 'repeat', 'weeks' => 2])->assertSessionHas('success');
        $this->assertSame(9, ShiftAssignment::count());
        $this->assertSame($night->id, ShiftAssignment::where('employee_id', $this->john->id)->whereDate('date', $mon->copy()->addWeeks(2)->toDateString())->value('shift_id'));
        $this->post('http://localhost/attendance/rota/copy', ['week' => $mon->copy()->addWeeks(2)->toDateString(), 'mode' => 'last_week'])->assertSessionHas('success');
        $this->assertSame(9, ShiftAssignment::count(), 'nothing overwritten');
        $this->post('http://localhost/attendance/rota/copy', ['week' => $mon->copy()->addWeeks(10)->toDateString(), 'mode' => 'last_week'])->assertSessionHas('error');

        // A supervisor (manager) only schedules the people they look after
        $mgr = $this->user('john.mgr@pikozm.com', 'manager', $this->john->id);
        $this->actingAs($mgr)->get('http://localhost/attendance/shifts')->assertForbidden();
        $this->get('http://localhost/attendance/rota?week=' . $d(0))->assertOk()->assertSee('The people you look after')->assertDontSee('Mary Banda');
        $this->postJson('http://localhost/attendance/rota', ['cells' => [['employee_id' => $this->mary->id, 'date' => $d(4), 'value' => $morning->id]]])->assertStatus(422);
        $this->postJson('http://localhost/attendance/rota', ['cells' => [['employee_id' => $this->john->id, 'date' => $d(4), 'value' => $morning->id]]])->assertOk();
        $this->mary->forceFill(['supervisor' => 'John Phiri'])->save();
        $this->postJson('http://localhost/attendance/rota', ['cells' => [['employee_id' => $this->mary->id, 'date' => $d(4), 'value' => $morning->id]]])->assertOk();
        $this->get('http://localhost/attendance/rota?week=' . $d(0))->assertSee('Mary Banda');

        // Retiring a shift that's on the rota keeps history, takes it off future days
        $this->actingAs($this->admin)->delete('http://localhost/attendance/shifts/' . $night->id)->assertSessionHas('success');
        $this->assertFalse($night->fresh()->active);
        $this->assertSame(0, ShiftAssignment::where('shift_id', $night->id)->whereDate('date', '>=', today())->count());
    }

    public function test_employee_sees_their_shifts_on_the_portal(): void
    {
        $shift = Shift::create(['name' => 'Afternoon', 'code' => 'A', 'start_time' => '14:00', 'end_time' => '22:00', 'color' => '#C8742B']);
        ShiftAssignment::create(['employee_id' => $this->mary->id, 'date' => today()->addDay()->toDateString(), 'shift_id' => $shift->id]);
        ShiftAssignment::create(['employee_id' => $this->mary->id, 'date' => today()->addDays(2)->toDateString(), 'shift_id' => null]);
        ShiftAssignment::create(['employee_id' => $this->john->id, 'date' => today()->addDay()->toDateString(), 'shift_id' => $shift->id]);

        $me = $this->user('mary.rota@pikozm.com', 'employee', $this->mary->id);
        $this->actingAs($me)->get('http://localhost/portal/attendance')->assertOk()
            ->assertSee('My shifts')->assertSee('Afternoon')->assertSee('14:00–22:00')->assertSee('Tomorrow')->assertSee('Day off');
        $this->get('http://localhost/attendance/rota')->assertRedirect();
    }
}
