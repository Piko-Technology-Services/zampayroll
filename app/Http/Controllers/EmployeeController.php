<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\PayrollEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST ALL EMPLOYEES
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $employees = Employee::latest()->paginate(10);

        return view('dashboard.employees.index', compact('employees'));
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW CREATE FORM
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        return view('dashboard.employees.create');
    }

    /*
    |--------------------------------------------------------------------------
    | STORE EMPLOYEE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'date_of_birth' => 'required|date',
            'gender' => 'required',
            'primary_phone' => 'required',
            'position' => 'required',
            'leave_days_entitled' => 'nullable|numeric|min:0',
            'leave_days_balance' => 'nullable|numeric|min:0',
        ]);

        // Generate Employee ID (EIN)
        $employeeId = 'EMP-' . date('Y') . '-' . strtoupper(Str::random(5));

        // Handle passport photo upload
        $passportPath = null;
        if ($request->hasFile('passport_photo')) {
            $file = $request->file('passport_photo');
            $passportPath = $file->store('uploads/employees/passports', 'public');
        }

        $employee = Employee::create([
            'employee_id' => $employeeId,
            'first_name' => $request->first_name,
            'middle_name' => $request->middle_name,
            'last_name' => $request->last_name,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'nationality' => $request->nationality,
            'nrc_no' => $request->nrc_no,
            'passport_number' => $request->passport_number,
            'passport_photo' => $passportPath,

            'personal_email' => $request->personal_email,
            'company_email' => $request->company_email,
            'primary_phone' => $request->primary_phone,
            'secondary_phone' => $request->secondary_phone,

            'position' => $request->position,
            'department' => $request->department,
            'branch' => $request->branch,
            'supervisor' => $request->supervisor,
            'employment_status' => 'Active',

            'probation_start' => $request->probation_start,
            'probation_end' => $request->probation_end,
            'contract_start' => $request->contract_start,
            'contract_end' => $request->contract_end,

            'emergency_name' => $request->emergency_name,
            'emergency_relationship' => $request->emergency_relationship,
            'emergency_phone' => $request->emergency_phone,

            'next_of_kin_name' => $request->next_of_kin_name,
            'next_of_kin_phone' => $request->next_of_kin_phone,
            'next_of_kin_address' => $request->next_of_kin_address,

            'bank_name' => $request->bank_name,
            'bank_account_no' => $request->bank_account_no,
            'ssn' => $request->ssn,
            'nhima_no' => $request->nhima_no,
            'tpin' => $request->tpin,
            'net_salary' => $request->net_salary,
            'leave_days_entitled' => $request->leave_days_entitled,
            'leave_days_balance' => $request->leave_days_balance,

            'uploads' => null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | HANDLE MULTIPLE DOCUMENT UPLOADS
        |--------------------------------------------------------------------------
        */
        if ($request->hasFile('documents')) {

            $uploads = [];

            foreach ($request->file('documents') as $file) {
                $path = $file->store('uploads/employees/documents', 'public');

                $uploads[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'uploaded_at' => now()->toDateTimeString(),
                ];
            }

            $employee->update([
                'uploads' => $uploads
            ]);
        }

        // Seed natural_gross_salary / daily_rate_salary / leave_days_value
        // /leave_days_balance so leave, overtime, and other modules have
        // values to work with right away. Wrapped defensively — priming
        // depends on payroll_rules being seeded, and a failure here should
        // never block employee creation.
        $this->primeDerivedSalaryValues($employee);

        return redirect()->route('employees.index')
            ->with('success', 'Employee created successfully');
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW SINGLE EMPLOYEE
    |--------------------------------------------------------------------------
    */
    public function show(Employee $employee)
    {
        return view('dashboard.employees.show', compact('employee'));
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT FORM
    |--------------------------------------------------------------------------
    */
    public function edit(Employee $employee)
    {
        return view('dashboard.employees.edit', compact('employee'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE EMPLOYEE
    |--------------------------------------------------------------------------
    */
public function update(Request $request, Employee $employee)
{
    // 1. VALIDATION
    $request->validate([
        'first_name' => 'required',
        'last_name' => 'required',
        'date_of_birth' => 'nullable|date',
        'gender' => 'nullable|string',
        'primary_phone' => 'nullable',
        'position' => 'required',
        'net_salary' => 'nullable|numeric',
        'leave_days_entitled' => 'nullable|numeric|min:0',
        'leave_days_balance' => 'nullable|numeric|min:0',
    ]);

    // 2. SAFE DATA (NO $request->all())
    $employee->first_name = $request->first_name;
    $employee->middle_name = $request->middle_name;
    $employee->last_name = $request->last_name;
    $employee->date_of_birth = $request->date_of_birth;
    $employee->gender = $request->gender;
    $employee->nationality = $request->nationality;
    $employee->nrc_no = $request->nrc_no;
    $employee->passport_number = $request->passport_number;

    $employee->personal_email = $request->personal_email;
    $employee->company_email = $request->company_email;
    $employee->primary_phone = $request->primary_phone;
    $employee->secondary_phone = $request->secondary_phone;

    $employee->position = $request->position;
    $employee->department = $request->department;
    $employee->supervisor = $request->supervisor;
    $employee->branch = $request->branch;
    // $employee->employment_status = $request->employment_status;
    $employee->employment_status = $request->employment_status ?? 'Active';

    $employee->probation_start = $request->probation_start;
    $employee->probation_end = $request->probation_end;
    $employee->contract_start = $request->contract_start;
    $employee->contract_end = $request->contract_end;

    $employee->emergency_name = $request->emergency_name;
    $employee->emergency_relationship = $request->emergency_relationship;
    $employee->emergency_phone = $request->emergency_phone;

    $employee->next_of_kin_name = $request->next_of_kin_name;
    $employee->next_of_kin_phone = $request->next_of_kin_phone;
    $employee->next_of_kin_address = $request->next_of_kin_address;

    $employee->bank_name = $request->bank_name;
    $employee->bank_account_no = $request->bank_account_no;
    $employee->ssn = $request->ssn;
    $employee->nhima_no = $request->nhima_no;
    $employee->tpin = $request->tpin;

    $employee->net_salary = $request->net_salary ? (float) $request->net_salary : null;
    $employee->leave_days_entitled = $request->leave_days_entitled !== null && $request->leave_days_entitled !== ''
        ? (float) $request->leave_days_entitled
        : null;
    $employee->leave_days_balance = $request->leave_days_balance !== null && $request->leave_days_balance !== ''
        ? (float) $request->leave_days_balance
        : null;

    // 3. PASSPORT PHOTO UPLOAD
    if ($request->hasFile('passport_photo')) {
        $path = $request->file('passport_photo')
            ->store('uploads/employees/passports', 'public');

        $employee->passport_photo = $path;
    }

    // 4. DOCUMENT UPLOADS (MERGE WITH EXISTING)
    if ($request->hasFile('documents')) {

        $uploads = $employee->uploads ?? [];

        foreach ($request->file('documents') as $file) {
            $path = $file->store('uploads/employees/documents', 'public');

            $uploads[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'uploaded_at' => now()->toDateTimeString(),
            ];
        }

        $employee->uploads = $uploads;
    }

    // 5. SAVE
    $employee->save();

    // 6. Re-prime derived salary values in case salary, assignments, or
    //    entitlements changed. Same defensive wrapper as store().
    $this->primeDerivedSalaryValues($employee);

    return redirect()->route('employees.index')
        ->with('success', 'Employee updated successfully');
}

    /*
    |--------------------------------------------------------------------------
    | DELETE EMPLOYEE
    |--------------------------------------------------------------------------
    */
    public function destroy(Employee $employee)
    {
        $employee->delete();

        return redirect()->route('employees.index')
            ->with('success', 'Employee deleted successfully');
    }



    
public function downloadSampleCsv()
{
    $headers = [
        'employee_id',
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'gender',
        'nationality',
        'nrc_no',
        'passport_number',
        'personal_email',
        'primary_phone',
        'secondary_phone',
        'position',
        'department',
        'branch',
        'supervisor',
        'employment_status',
        'probation_start',
        'probation_end',
        'contract_start',
        'contract_end',
        'emergency_name',
        'emergency_relationship',
        'emergency_phone',
        'next_of_kin_name',
        'next_of_kin_phone',
        'next_of_kin_address',
        'bank_name',
        'bank_account_no',
        'ssn',
        'nhima_no',
        'tpin',
        'net_salary',
    ];

    $filename = 'employee_import_template.csv';

    $callback = function () use ($headers) {
        $file = fopen('php://output', 'w');

        // header row
        fputcsv($file, $headers);

        // sample row
        fputcsv($file, [
            'EMP001',
            'John',
            'Michael',
            'Doe',
            '1995-06-10',
            'Male',
            'Zambian',
            '123456/78/9',
            'P1234567',
            'john@example.com',
            '0977000000',
            '0966000000',
            'Accountant',
            'Finance',
            'Head Office',
            'Jane Manager',
            'Active',
            '2025-01-01',
            '2025-03-01',
            '2025-04-01',
            '2027-04-01',
            'Mary Doe',
            'Mother',
            '0977000001',
            'Peter Doe',
            '0977000002',
            'Lusaka',
            'Zanaco',
            '1234567890',
            'SSN12345',
            'NHIMA98765',
            'TIN98765',
            '5000',
        ]);

        fclose($file);
    };

    return response()->streamDownload($callback, $filename);
}

public function import(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:csv,txt'
    ]);

    $file = fopen($request->file('file')->getRealPath(), 'r');

    $header = array_map('trim', fgetcsv($file));

    while ($row = fgetcsv($file)) {

        $data = array_combine($header, $row);

        $employee = Employee::create([
            'employee_id' => $data['employee_id'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'nrc_no' => $data['nrc_no'] ?? null,
            'passport_number' => $data['passport_number'] ?? null,
            'passport_photo' => $data['passport_photo'] ?? null,

            'personal_email' => $data['personal_email'] ?? null,
            'company_email' => $data['company_email'] ?? null,
            'primary_phone' => $data['primary_phone'] ?? null,
            'secondary_phone' => $data['secondary_phone'] ?? null,

            'position' => $data['position'] ?? null,
            'department' => $data['department'] ?? null,
            'branch' => $data['branch'] ?? null,
            'supervisor' => $data['supervisor'] ?? null,
            'employment_status' => $data['employment_status'] ?? 'Active',

            'probation_start' => $data['probation_start'] ?? null,
            'probation_end' => $data['probation_end'] ?? null,
            'contract_start' => $data['contract_start'] ?? null,
            'contract_end' => $data['contract_end'] ?? null,

            'emergency_name' => $data['emergency_name'] ?? null,
            'emergency_relationship' => $data['emergency_relationship'] ?? null,
            'emergency_phone' => $data['emergency_phone'] ?? null,

            'next_of_kin_name' => $data['next_of_kin_name'] ?? null,
            'next_of_kin_phone' => $data['next_of_kin_phone'] ?? null,
            'next_of_kin_address' => $data['next_of_kin_address'] ?? null,

            'bank_name' => $data['bank_name'] ?? null,
            'bank_account_no' => $data['bank_account_no'] ?? null,
            'ssn' => $data['ssn'] ?? null,
            'nhima_no' => $data['nhima_no'] ?? null,
            'tpin' => $data['tpin'] ?? null,
            'net_salary' => $data['net_salary'] ?? null,
            'leave_days_entitled' => $data['leave_days_entitled'] ?? null,
            'leave_days_balance' => $data['leave_days_balance'] ?? null,
        ]);

        // Same priming as the single-employee create flow, so imported
        // employees get natural_gross_salary / daily_rate_salary too.
        $this->primeDerivedSalaryValues($employee);
    }

    fclose($file);

    return back()->with('success', 'Employees imported successfully.');
}

    /*
    |--------------------------------------------------------------------------
    | PRIME DERIVED SALARY VALUES (natural_gross_salary, daily_rate_salary,
    | leave_days_value / leave_days_balance seeds) via PayrollEngine.
    |
    | NOTE: PayrollEngine::primeEmployeeInitialValues() reads
    | $employee->net_salary as its target net figure. This form's "salary"
    | field is what's saved to the `salary` column — if your schema uses a
    | separate `net_salary` column set elsewhere (e.g. a payroll setup
    | screen), priming will use that value as-is. If `net_salary` isn't
    | populated yet for an employee, priming is skipped for that employee
    | (see the guard inside PayrollEngine) rather than guessing at a value.
    |--------------------------------------------------------------------------
    */
    private function primeDerivedSalaryValues(Employee $employee): void
    {
        try {
            (new PayrollEngine())->primeEmployeeInitialValues($employee);
        } catch (\Throwable $e) {
            \Log::warning('EmployeeController: failed to prime derived salary values', [
                'employee_id' => $employee->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

}