<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\Employee;
use Illuminate\Http\Request;
use App\Services\PayrollEngine;
use Barryvdh\DomPDF\Facade\Pdf;


class PayrollController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST ALL PAYROLLS
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $payrolls = Payroll::with('employee')
            ->latest()
            ->paginate(10);

        return view('dashboard.payroll.index', compact('payrolls'));
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW CREATE FORM
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        $employees = Employee::orderBy('first_name')->get();

        return view('dashboard.payroll.create', compact('employees'));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE PAYROLL (INITIAL CREATION)
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id,company_id,' . (int) auth()->user()->company_id,
            'pay_period'  => 'required'
        ]);

        $employee = Employee::findOrFail($request->employee_id);

        $payroll = Payroll::create([
            'employee_id'   => $employee->id,
            'pay_period'    => $request->pay_period,
            'company'       => (\App\Support\Tenancy::company()?->name ?? 'ZAMPAYROLL'),
            'branch'        => $employee->branch,
            'cost_centre'   => $employee->department,
            'date_engaged'  => $employee->contract_start,
            'salary_rate'   => $employee->net_salary ?? 0,
            'status'        => 'Draft',
        ]);

        return redirect()
            ->route('payroll.show', $payroll->id)
            ->with('success', 'Payroll created successfully');
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW SINGLE PAYROLL
    |--------------------------------------------------------------------------
    */
    // public function show(Payroll $payroll)
    // {
    //     $payroll->load(['employee', 'items']);

    //     return view('dashboard.payroll.show', compact('payroll'));
    // }

    public function show(Payroll $payroll)
    {
        $payslips = app(\App\Services\Payslips\Payslips::class);
        $payroll->load(['employee', 'items', 'run']);
        $name = trim($payroll->employee->first_name . ' ' . $payroll->employee->last_name);

        return view('payslips.document', [
            'slips'   => collect([$payslips->data($payroll)]),
            'mode'    => 'screen',
            'toolbar' => [
                'back'     => $payroll->run ? route('payroll.runs.payslips', $payroll->run) : url()->previous(),
                'title'    => 'Payslip — ' . $name,
                'subtitle' => ($payroll->run?->displayName() ?? $payroll->pay_period),
                'actions'  => array_values(array_filter([
                    ['href' => route('payroll.pdf', $payroll), 'label' => 'Download PDF'],
                    $payroll->run?->isLocked() && auth()->user()?->hasPermission('payroll.run')
                        ? ['href' => route('payroll.runs.payslips.share', ['run' => $payroll->run, 'only' => [$payroll->id]]), 'label' => 'Share…', 'class' => 'primary'] : null,
                ])),
            ],
        ]);
    }

    public function downloadPdf(Payroll $payroll)
    {
        $payslips = app(\App\Services\Payslips\Payslips::class);

        return $payslips->pdf($payroll)->download($payslips->filename($payroll));
    }

    private function buildSingleEmployeeRun(Payroll $payroll)
    {
        $run = $payroll->run; // belongsTo relation on Payroll → PayrollRun
        $run->setRelation('payrolls', collect([$payroll]));
 
        return $run;
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT PAYROLL
    |--------------------------------------------------------------------------
    */
    public function edit(Payroll $payroll)
    {
        $employees = Employee::orderBy('first_name')->get();

        return view('dashboard.payroll.edit', compact('payroll', 'employees'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE PAYROLL
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, Payroll $payroll)
    {
        if ($payroll->isLocked()) {
            return back()->with('error', 'This payslip belongs to a payroll that is in approval or locked.');
        }

        $request->validate([
            'employee_id' => 'required|exists:employees,id,company_id,' . (int) auth()->user()->company_id,
            'pay_period'  => 'required',
        ]);

        $employee = Employee::findOrFail($request->employee_id);

        $payroll->update([
            'employee_id'  => $employee->id,
            'pay_period'   => $request->pay_period,
            'branch'       => $employee->branch,
            'cost_centre'  => $employee->department,
            'date_engaged' => $employee->contract_start,
            'salary_rate'  => $employee->net_salary,
        ]);

        return redirect()
            ->route('payroll.index')
            ->with('success', 'Payroll updated successfully');
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE PAYROLL
    |--------------------------------------------------------------------------
    */
    public function destroy(Payroll $payroll)
    {
        if ($payroll->isLocked()) {
            return back()->with('error', 'This payslip belongs to a payroll that is in approval or locked.');
        }

        $payroll->delete();

        return redirect()
            ->route('payroll.index')
            ->with('success', 'Payroll deleted successfully');
    }

    /*
    |--------------------------------------------------------------------------
    | PROCESS PAYROLL (CALCULATE NET PAY)
    |--------------------------------------------------------------------------
    */

    public function process(Payroll $payroll, PayrollEngine $engine)
    {
        if ($payroll->isLocked()) {
            return back()->with('error', 'This payslip belongs to a payroll that is in approval or locked.');
        }

        // 1. BUILD PAYROLL ITEMS AUTOMATICALLY
        $engine->build($payroll);

        // 2. CALCULATE TOTALS
        $income = $payroll->items()
            ->where('type', 'earning')
            ->sum('amount');

        $deductions = $payroll->items()
            ->where('type', 'deduction')
            ->sum('amount');

        $net = $income - $deductions;

        // 3. UPDATE PAYROLL
        $payroll->update([
            'total_income'     => $income,
            'total_deductions' => $deductions,
            'net_pay'          => $net,
            'status'           => 'Processed'
        ]);

        return back()->with('success', 'Payroll fully processed by engine.');
    }

    /*
    |--------------------------------------------------------------------------
    | PRINT PAYROLL (OPTIONAL BUT USEFUL)
    |--------------------------------------------------------------------------
    */
    public function print(Payroll $payroll)
    {
        return $this->show($payroll);
    }
}