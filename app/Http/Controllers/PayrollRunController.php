<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Models\Payroll;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollRule;
use App\Models\PayrollRunAdjustment;
use App\Services\PayrollEngine;
use App\Services\LoanRepaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PayrollAdjustmentTemplateExport;
use App\Imports\PayrollAdjustmentImport;

class PayrollRunController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST ALL PAYROLL RUNS (excludes hidden and trashed)
    |--------------------------------------------------------------------------
    |
    | Supports:
    |   - free-text search (?q=)              → alias, period, or run id
    |   - status filter (?status=)             → Draft / Processing / Processed / Approved / Paid
    |   - year → month drill-down (?year=&month=), driven by the PAY PERIOD
    |
    | `period` is free text ("2026-07", "July 2026", etc.), not a real date
    | column, so we can't reliably filter it with whereYear()/whereMonth() in
    | SQL. Instead PayrollRun::period_date (accessor, see the model) parses
    | it into a Carbon date, and filtering/grouping happens in memory against
    | that. Fine at payroll-run volumes (a handful of rows per month).
    |
    */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $year   = $request->query('year');
        $month  = $request->query('month');

        $allRuns = PayrollRun::with('payrolls.employee')->visible()->get();

        $filtered = $allRuns;

        if ($search !== '') {
            $needle = strtolower($search);
            $filtered = $filtered->filter(fn ($run) =>
                str_contains(strtolower($run->alias ?? ''), $needle) ||
                str_contains(strtolower($run->period ?? ''), $needle) ||
                (string) $run->id === $search
            );
        }

        if ($status) {
            $filtered = $filtered->filter(fn ($run) => $run->status === $status);
        }

        if ($year) {
            $filtered = $filtered->filter(fn ($run) => $run->period_date && (int) $run->period_date->year === (int) $year);
        }

        if ($month) {
            $filtered = $filtered->filter(fn ($run) => $run->period_date && (int) $run->period_date->month === (int) $month);
        }

        $runs = $filtered
            ->sortByDesc(fn ($run) => $run->period_date ?? $run->created_at)
            ->values();

        $hiddenCount  = PayrollRun::hiddenOnly()->count();
        $trashedCount = PayrollRun::onlyTrashed()->count();

        // Years available to drill into — computed from ALL visible runs
        // (unfiltered by year/month) so the year chip row never disappears.
        $availableYears = $allRuns
            ->map(fn ($run) => $run->period_date?->year)
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        $activeYear = $year ?: ($availableYears->first() ?? now()->year);

        // Month chip counts for whichever year is active.
        $monthCounts = $allRuns
            ->filter(fn ($run) => $run->period_date && (int) $run->period_date->year === (int) $activeYear)
            ->groupBy(fn ($run) => $run->period_date->month)
            ->map->count();

        // Runs whose period couldn't be parsed at all — surfaced so HR can fix them,
        // rather than having them silently vanish from every year/month filter.
        $unparsedCount = $allRuns->filter(fn ($run) => $run->period_date === null)->count();

        $statusOptions = ['Draft', 'Processing', 'Processed', 'Approved', 'Paid'];

        return view('dashboard.payroll.runs.index', compact(
            'runs',
            'hiddenCount',
            'trashedCount',
            'availableYears',
            'activeYear',
            'monthCounts',
            'unparsedCount',
            'statusOptions',
            'search',
            'status',
            'year',
            'month'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | HIDDEN RUNS — archived out of the main view, not deleted
    |--------------------------------------------------------------------------
    */
    public function hiddenIndex()
    {
        $runs = PayrollRun::with('payrolls.employee')
            ->hiddenOnly()
            ->latest('hidden_at')
            ->get();

        return view('dashboard.payroll.runs.hidden', compact('runs'));
    }

    /*
    |--------------------------------------------------------------------------
    | TRASH — soft-deleted runs, recoverable until force-deleted
    |--------------------------------------------------------------------------
    */
    public function trashIndex()
    {
        $runs = PayrollRun::onlyTrashed()
            ->with('payrolls.employee')
            ->latest('deleted_at')
            ->get();

        return view('dashboard.payroll.runs.trash', compact('runs'));
    }

    /*
    |--------------------------------------------------------------------------
    | HIDE / UNHIDE
    |--------------------------------------------------------------------------
    */
    public function hide(PayrollRun $run)
    {
        $run->update(['hidden_at' => now()]);

        return back()->with('success', 'Payroll run hidden. Find it again under Hidden.');
    }

    public function unhide(PayrollRun $run)
    {
        $run->update(['hidden_at' => null]);

        return back()->with('success', 'Payroll run restored to the main view.');
    }

    /*
    |--------------------------------------------------------------------------
    | MOVE TO TRASH (soft delete) — this is what the card's Delete control does
    |--------------------------------------------------------------------------
    */
    public function trash(PayrollRun $run)
    {
        if (! $run->isEditable()) {
            return back()->with('error', 'Approved or in-approval payrolls can\'t be deleted — they are part of your records.');
        }

        $run->delete(); // soft delete — recoverable from the Trash view

        return redirect()->route('payroll.runs.index')
            ->with('success', 'Payroll run moved to trash.');
    }

    /*
    |--------------------------------------------------------------------------
    | RESTORE FROM TRASH
    |--------------------------------------------------------------------------
    */
    public function restore($id)
    {
        $run = PayrollRun::onlyTrashed()->findOrFail($id);
        $run->restore();

        return redirect()->route('payroll.runs.trash')
            ->with('success', 'Payroll run restored.');
    }

    /*
    |--------------------------------------------------------------------------
    | PERMANENTLY DELETE — only reachable from the Trash view
    |--------------------------------------------------------------------------
    |
    | NOTE: this deletes the payroll_runs row itself. It does NOT cascade
    | to related payrolls/payroll_items/payroll_run_adjustments here — check
    | your foreign key constraints (cascadeOnDelete vs restrict) on those
    | tables. If this throws a constraint violation, either add
    | cascadeOnDelete() to those migrations or delete related payrolls
    | first before calling forceDelete().
    |
    */
    public function forceDelete($id)
    {
        $run = PayrollRun::onlyTrashed()->findOrFail($id);
        $run->forceDelete();

        return redirect()->route('payroll.runs.trash')
            ->with('success', 'Payroll run permanently deleted.');
    }

    /*
    |--------------------------------------------------------------------------
    | PAYSLIPS VIEW
    |--------------------------------------------------------------------------
    */
    public function payslips($runId)
    {
        $run = PayrollRun::with(['payrolls.employee', 'payrolls.items'])
            ->findOrFail($runId);

        $companyName = (\App\Support\Tenancy::company()?->name ?? 'ZAMPAYROLL');
        $companyLogo = 'http://misc.zampayroll.com/logo.png';
        

        return view('dashboard.payroll.runs.payslips', compact('run', 'companyName' ,'companyLogo'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE NEW RUN
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        return view('dashboard.payroll.runs.create');
    }

    /*
    |--------------------------------------------------------------------------
    | STORE NEW RUN
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'period' => 'required',
            'alias'  => 'nullable|string|max:255',
        ]);

        $run = PayrollRun::create([
            'period'     => $request->period,
            'alias'      => $request->alias,
            'created_by' => auth()->id(),
            'status'     => 'Draft',
        ]);

        return redirect()->route('payroll.runs.show', $run->id);
    }
    /*
    |--------------------------------------------------------------------------
    | SHOW RUN
    |--------------------------------------------------------------------------
    */
    public function show(PayrollRun $run)
    {
        $run->load('payrolls.employee', 'payrolls.items');
        $rules = PayrollRule::where(
            'requires_assignment',
            true
        )->get();


        return view('dashboard.payroll.runs.show', compact('run','rules'));
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE PAYROLL FOR ALL ACTIVE EMPLOYEES
    |--------------------------------------------------------------------------
    |
    | Loan integration: for each employee, BEFORE the engine builds their
    | payslip, LoanRepaymentService checks their active loans and creates
    | a "Loan Repayment" deduction adjustment for any loan due this period
    | (per its payment_plan). The engine then reads that adjustment like
    | any other and includes it on the payslip automatically. AFTER the
    | payslip is built, recordDeductions() confirms it landed and posts
    | the payment against the loan's running balance in the ledger.
    |
    */
    public function generate(PayrollRun $run)
    {
        if (! $run->isEditable()) {
            return back()->with('error', 'This payroll is waiting for approval or already locked. Recall it to make changes.');
        }

        // Same routine AI mode uses ("prepare this month's payroll").
        app(\App\Services\HrActions::class)->generatePayroll($run, auth()->user());

        return redirect()->route('payroll.runs.show', $run)->with('success', 'Recalculated. Check the highlights below.');
    }

    /*
    |--------------------------------------------------------------------------
    | STORE ADJUSTMENT — saves to payroll_run_adjustments, then rebuilds
    |--------------------------------------------------------------------------
    |
    | Flow:
    |   1. Validate — tax_profile required for earnings, ignored for deductions
    |   2. Guard — cannot adjust a finalized run
    |   3. Save adjustment record
    |   4. engine->build() wipes and rewrites the entire payslip from scratch
    |      so PAYE, NAPSA, Basic Pay are all recalculated against the new base
    |
    */
    public function storeAdjustment(
        Request $request,
        PayrollRun $run,
        PayrollEngine $engine
    ) {
        if (! $run->isEditable()) {
            return response()->json(['success' => false, 'message' => 'This payroll is waiting for approval or already locked. Recall it to make changes.'], 423);
        }

        $request->validate([
            'employee_id'  => 'required|exists:employees,id,company_id,' . (int) auth()->user()->company_id,
            'name'         => 'required|string|max:255',
            'type'         => 'required|in:earning,deduction,system',
            'formula_type' => 'required|in:fixed,percentage',
            'value'        => 'required|numeric|min:0',

            // tax_profile only matters for earnings:
            //   taxable     → PAYE + NAPSA  (Overtime, Bonus, Commission)
            //   napsa_only  → NAPSA only    (Gratuity, Payment in lieu of notice, Ex gratia)
            //   non_taxable → neither       (Reimbursements)
            'tax_profile' => [
                'nullable',
                'required_if:type,earning',
                'in:taxable,napsa_only,non_taxable',
            ],
            'rule_id' => 'nullable|string|max:255'
        ]);

        // Save the adjustment record — engine reads this on build()
        PayrollRunAdjustment::create([
            'payroll_run_id' => $run->id,
            'employee_id'    => $request->employee_id,
            'payroll_rule_id'        => $request->rule_id,
            'name'           => $request->name,
            'type'           => $request->type,
            'formula_type'   => $request->formula_type,
            'value'          => $request->value,
            'tax_profile'    => $request->type === 'earning'
                                    ? $request->tax_profile
                                    : null,   // deductions don't use tax_profile
            'active'         => true,
            
        ]);

        // Find or create the payroll record for this employee in this run
        $payroll = Payroll::where('payroll_run_id', $run->id)
            ->where('employee_id', $request->employee_id)
            ->first();

        if (! $payroll) {
            return back()->with(
                'error',
                'Generate payroll for this employee before adding adjustments.'
            );
        }

        // Rebuild — engine wipes all items and recalculates everything from scratch,
        // including PAYE and NAPSA on the new bases with this adjustment included.
        $engine->build($payroll);

        Log::info('Payroll adjustment added and payslip rebuilt', [
            'run_id'      => $run->id,
            'employee_id' => $request->employee_id,
            'name'        => $request->name,
            'type'        => $request->type,
            'tax_profile' => $request->tax_profile,
            'value'       => $request->value,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Adjustment saved and payroll recalculated.',
        ]);
    }

    public function downloadAdjustmentTemplate(
        PayrollRun $run, PayrollRule $rule
    ) {
        $file_name = "ADJ-" . $rule->code . "-" . $rule->name . "-" . $run->alias . ".csv";

        return Excel::download(
            new PayrollAdjustmentTemplateExport($run),
             $file_name
        );
    }

    public function importAdjustmentsExcel(
        Request $request,
        PayrollRun $run
    ) {
        $request->validate([
            'rule_id' => 'required|exists:payroll_rules,id,company_id,' . (int) auth()->user()->company_id,
            'file' => 'required|file|mimes:csv'
        ]);

        if (! $run->isEditable()) {
            return back()->with('error', 'This payroll is waiting for approval or already locked. Recall it to make changes.');
        }

        Excel::import(
            new PayrollAdjustmentImport(
                $run,
                $request->rule_id
            ),
            $request->file('file')
        );

        return back()->with(
            'success',
            'Adjustments imported successfully.'
        );
    }

    


    /*
    |--------------------------------------------------------------------------
    | DELETE ADJUSTMENT — removes the record, then rebuilds
    |--------------------------------------------------------------------------
    */
    public function destroyAdjustment(
        PayrollRunAdjustment $adjustment,
        PayrollEngine $engine
    ) {
        $run = $adjustment->run;

        if ($run && ! $run->isEditable()) {
            return response()->json(['success' => false, 'message' => 'This payroll is waiting for approval or already locked. Recall it to make changes.'], 423);
        }

        $payroll = Payroll::where('payroll_run_id', $adjustment->payroll_run_id)
            ->where('employee_id', $adjustment->employee_id)
            ->first();

        $adjustment->delete();

        if ($payroll) {
            // Rebuild without the deleted adjustment
            $engine->build($payroll);
        }

        return response()->json([
            'success' => true,
            'message' => 'Adjustment removed and payroll recalculated.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FINALIZE RUN
    |--------------------------------------------------------------------------
    */
    public function finalize(PayrollRun $run)
    {
        // Runs are no longer locked in one click: they go through the approval chain.
        return redirect()->route('payroll.runs.show', $run)
            ->with('info', 'Payroll is now approved through your approval chain. Submit it for approval below.');
    }

    /*
    |--------------------------------------------------------------------------
    | EMPLOYEE SUMMARY (partial view)
    |--------------------------------------------------------------------------
    */
    public function employeeSummary(PayrollRun $run, Payroll $payroll)
    {
        $payroll->load(['employee', 'items']);

        return view(
            'dashboard.payroll.runs.partials.employee-summary',
            compact('payroll')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE FIELD (inline edit on a payroll item)
    |--------------------------------------------------------------------------
    |
    | NOTE: This is a direct item override — it does NOT re-run the engine.
    | Use only for minor corrections (e.g. fixing a description or a one-off
    | amount override). Statutory deductions will NOT be recalculated.
    | For changes that affect tax, use storeAdjustment() instead.
    |
    */
    public function updateField(Request $request, PayrollItem $item)
    {
        $request->validate([
            'field' => 'required|in:description,amount,code',
            'value' => 'required',
        ]);

        $payroll = $item->payroll;

        if ($payroll->isLocked()) {
            return response()->json(['success' => false, 'message' => 'This payroll is waiting for approval or already locked. Recall it to make changes.'], 423);
        }

        Log::info('Payroll item field updated (direct override)', [
            'item_id'    => $item->id,
            'payroll_id' => $item->payroll_id,
            'field'      => $request->field,
            'old_value'  => $item->{$request->field},
            'new_value'  => $request->value,
        ]);

        $item->{$request->field} = $request->value;
        $item->save();

        // Recalculate totals from items (direct sum — engine not re-run)
        $this->syncPayrollTotals($payroll);

        return response()->json(['success' => true, 'message' => 'Updated successfully.']);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE ITEM (direct payroll item removal)
    |--------------------------------------------------------------------------
    |
    | Same caveat as updateField — use only for non-statutory line items.
    | Removing PAYE or NAPSA this way will leave totals inconsistent.
    |
    */
    public function deleteItem(PayrollItem $item)
    {
        $payroll = $item->payroll;

        if ($payroll && $payroll->isLocked()) {
            return response()->json(['success' => false, 'message' => 'This payroll is waiting for approval or already locked. Recall it to make changes.'], 423);
        }

        $item->delete();

        if ($payroll) {
            $this->syncPayrollTotals($payroll);
        }

        return response()->json([
            'success' => true,
            'message' => 'Item deleted.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SYNC PAYROLL TOTALS (private — sums items into payroll record)
    |--------------------------------------------------------------------------
    |
    | Only used after direct item edits (updateField / deleteItem).
    | All adjustment-driven changes go through engine->build() instead.
    |
    */
    private function syncPayrollTotals(Payroll $payroll): void
    {
        $income     = $payroll->items()->where('type', 'earning')->sum('amount');
        $deductions = $payroll->items()->where('type', 'deduction')->sum('amount');

        $payroll->update([
            'total_income'     => $income,
            'total_deductions' => $deductions,
            'net_pay'          => $income - $deductions,
        ]);
    }
}
