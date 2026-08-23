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
        $employees = Employee::latest()->get();

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
        'employee_id'=> 'required',
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
    $employee->employee_id = $request->employee_id;
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
        'primary_phone',
        'personal_email',
        'position',
        'department',
        'branch',
        'supervisor',
        'contract_start',
        'contract_end',
        'bank_name',
        'bank_account_no',
        'ssn',
        'nhima_no',
        'tpin',
        'net_salary',
        'leave_days_entitled',
        'leave_days_balance',
    ];

    $filename = 'employee_import_template.csv';

    $callback = function () use ($headers) {
        $file = fopen('php://output', 'w');

        // Header row
        fputcsv($file, $headers);

        // Sample row
        fputcsv($file, [
            'EMP001',
            'John',
            'Michael',
            'Doe',
            '1995-06-10',
            'Male',
            'Zambian',
            '123456/78/9',
            '0977000000',
            'john@example.com',
            'Accountant',
            'Finance',
            'Head Office',
            'Jane Manager',
            '2025-04-01',
            '2027-04-01',
            'Zanaco',
            '1234567890',
            'SSN12345',
            'NHIMA98765',
            'TIN98765',
            '5000',
            '24',
            '24',
        ]);

        fclose($file);
    };

    return response()->streamDownload(
        $callback,
        $filename,
        ['Content-Type' => 'text/csv']
    );
}

public function import(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:csv,txt',
    ]);

    $file = fopen($request->file('file')->getRealPath(), 'r');

    if (!$file) {
        return back()->with('error', 'Unable to open the CSV file.');
    }

    /*
    |--------------------------------------------------------------------------
    | READ HEADER
    |--------------------------------------------------------------------------
    */

    $header = fgetcsv($file);

    if (!$header) {
        fclose($file);

        return back()->with('error', 'The CSV file is empty.');
    }

    // Remove BOM and whitespace from headers
    $header = array_map(function ($value) {
        return trim(str_replace("\xEF\xBB\xBF", '', $value));
    }, $header);

    /*
    |--------------------------------------------------------------------------
    | REQUIRED CSV HEADERS
    |--------------------------------------------------------------------------
    */

    $requiredHeaders = [
        'employee_id',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'position',
        'net_salary',
    ];

    $missingHeaders = array_diff($requiredHeaders, $header);

    if (!empty($missingHeaders)) {
        fclose($file);

        return back()->with(
            'error',
            'The CSV is missing the following required columns: ' .
            implode(', ', $missingHeaders)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORT EMPLOYEES
    |--------------------------------------------------------------------------
    */

    $imported = 0;
    $failed = 0;
    $errors = [];

    $rowNumber = 1;

    while (($row = fgetcsv($file)) !== false) {

        $rowNumber++;

        // Skip completely empty rows
        if (
            empty(array_filter($row, function ($value) {
                return trim((string) $value) !== '';
            }))
        ) {
            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | PROTECT AGAINST INCORRECT COLUMN COUNT
        |--------------------------------------------------------------------------
        */

        if (count($header) !== count($row)) {

            $failed++;

            $errors[] = "Row {$rowNumber}: Number of columns does not match the CSV header.";

            continue;
        }

        $data = array_combine($header, $row);

        /*
        |--------------------------------------------------------------------------
        | CLEAN DATA
        |--------------------------------------------------------------------------
        */

        $data = array_map(function ($value) {
            return is_string($value)
                ? trim($value)
                : $value;
        }, $data);

        /*
        |--------------------------------------------------------------------------
        | VALIDATE REQUIRED VALUES
        |--------------------------------------------------------------------------
        */

        $requiredFields = [
            'employee_id',
            'first_name',
            'last_name',
            'date_of_birth',
            'gender',
            'position',
            'net_salary',
        ];

        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (
                !isset($data[$field]) ||
                trim((string) $data[$field]) === ''
            ) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {

            $failed++;

            $errors[] =
                "Row {$rowNumber}: Missing required fields: " .
                implode(', ', $missingFields);

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATE DATE OF BIRTH
        |--------------------------------------------------------------------------
        */

        try {

            if (!empty($data['date_of_birth'])) {
                \Carbon\Carbon::parse($data['date_of_birth']);
            }

        } catch (\Throwable $e) {

            $failed++;

            $errors[] =
                "Row {$rowNumber} ({$data['employee_id']}): Invalid date of birth.";

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDATE SALARY
        |--------------------------------------------------------------------------
        */

        if (
            !is_numeric($data['net_salary']) ||
            (float) $data['net_salary'] < 0
        ) {

            $failed++;

            $errors[] =
                "Row {$rowNumber} ({$data['employee_id']}): Net salary must be a valid number.";

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | CHECK DUPLICATE EMPLOYEE ID
        |--------------------------------------------------------------------------
        */

        $existingEmployee = Employee::where(
            'employee_id',
            $data['employee_id']
        )->first();

        if ($existingEmployee) {

            $failed++;

            $errors[] =
                "Row {$rowNumber}: Employee ID '{$data['employee_id']}' already exists.";

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | LEAVE DAYS
        |--------------------------------------------------------------------------
        */

        $leaveDaysEntitled = (
            isset($data['leave_days_entitled']) &&
            $data['leave_days_entitled'] !== ''
        )
            ? (float) $data['leave_days_entitled']
            : null;

        $leaveDaysBalance = (
            isset($data['leave_days_balance']) &&
            $data['leave_days_balance'] !== ''
        )
            ? (float) $data['leave_days_balance']
            : $leaveDaysEntitled;

        /*
        |--------------------------------------------------------------------------
        | CREATE EMPLOYEE
        |--------------------------------------------------------------------------
        */

        try {

            $employee = Employee::create([

                /*
                |--------------------------------------------------------------------------
                | IDENTIFICATION
                |--------------------------------------------------------------------------
                */

                // IMPORTANT:
                // Employee ID comes directly from the CSV.
                'employee_id' => $data['employee_id'] ?? 'EMP-' . date('Y') . '-' . strtoupper(Str::random(5)),

                /*
                |--------------------------------------------------------------------------
                | PERSONAL INFORMATION
                |--------------------------------------------------------------------------
                */

                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],

                'date_of_birth' => !empty($data['date_of_birth'])
                    ? $data['date_of_birth']
                    : null,

                'gender' => $data['gender'] ?? null,

                'nationality' => !empty($data['nationality'])
                    ? $data['nationality']
                    : 'Zambian',

                'nrc_no' => $data['nrc_no'] ?? null,

                /*
                |--------------------------------------------------------------------------
                | CONTACT
                |--------------------------------------------------------------------------
                */

                'primary_phone' => $data['primary_phone'] ?? null,
                'personal_email' => $data['personal_email'] ?? null,

                /*
                |--------------------------------------------------------------------------
                | JOB
                |--------------------------------------------------------------------------
                */

                'position' => $data['position'],
                'department' => $data['department'] ?? null,
                'branch' => $data['branch'] ?? null,
                'supervisor' => $data['supervisor'] ?? null,

                // Automatically set new imports as Active
                'employment_status' => 'Active',

                /*
                |--------------------------------------------------------------------------
                | EMPLOYMENT DATES
                |--------------------------------------------------------------------------
                */

                'contract_start' => $data['contract_start'] ?? null,
                'contract_end' => $data['contract_end'] ?? null,

                /*
                |--------------------------------------------------------------------------
                | FINANCE
                |--------------------------------------------------------------------------
                */

                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_no' => $data['bank_account_no'] ?? null,

                'ssn' => $data['ssn'] ?? null,
                'nhima_no' => $data['nhima_no'] ?? null,
                'tpin' => $data['tpin'] ?? null,

                'net_salary' => (float) $data['net_salary'],

                /*
                |--------------------------------------------------------------------------
                | LEAVE
                |--------------------------------------------------------------------------
                */

                'leave_days_entitled' => $leaveDaysEntitled,
                'leave_days_balance' => $leaveDaysBalance,

                /*
                |--------------------------------------------------------------------------
                | SYSTEM FIELDS
                |--------------------------------------------------------------------------
                */

                'passport_photo' => null,
                'uploads' => null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | PRIME DERIVED SALARY VALUES
            |--------------------------------------------------------------------------
            */

            $this->primeDerivedSalaryValues($employee);

            $imported++;

        } catch (\Throwable $e) {

            $failed++;

            \Log::error('Employee CSV import failed', [
                'row' => $rowNumber,
                'employee_id' => $data['employee_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            $errors[] =
                "Row {$rowNumber} ({$data['employee_id']}): " .
                $e->getMessage();

            continue;
        }
    }

    fclose($file);

    /*
    |--------------------------------------------------------------------------
    | IMPORT RESULT
    |--------------------------------------------------------------------------
    */

    if ($failed > 0) {

        $message =
            "{$imported} employee(s) imported successfully. " .
            "{$failed} row(s) could not be imported.";

        if (!empty($errors)) {
            $message .= ' Errors: ' . implode(' | ', $errors);
        }

        return back()->with(
            $imported > 0 ? 'warning' : 'error',
            $message
        );
    }

    return back()->with(
        'success',
        "{$imported} employee(s) imported successfully."
    );
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