    /*
    |==========================================================================
    | BANKING — PRE-EXPORT CHECK
    |==========================================================================
    |
    | Shows which employees' bank_name couldn't be matched to a known bank
    | (so no SWIFT/BIC could be auto-filled) BEFORE the file is downloaded and
    | sent to the bank. Bulk payment files move real money — better to catch
    | a blank SWIFT code here than after upload.
    |
    */
    public function bankPaymentsPreview(PayrollRun $run)
    {
        $run->load(['payrolls.employee']);
        $company = auth()->user()->company;

        $templates = \App\Models\BankPaymentTemplate::where('company_id', $company->id)->get();

        $rows = $run->payrolls->map(function ($payroll) {
            $employee = $payroll->employee;
            $matchedBank = $employee ? \App\Models\Bank::findMatch($employee->bank_name ?? null) : null;

            return [
                'employee' => $employee,
                'matched_bank' => $matchedBank,
                'has_account_number' => (bool) ($employee->bank_account_no ?? null),
            ];
        });

        $unmatchedCount = $rows->filter(fn ($r) => !$r['matched_bank'])->count();
        $missingAccountCount = $rows->filter(fn ($r) => !$r['has_account_number'])->count();

        return view('dashboard.payroll.reports.bank-preview', compact('run', 'templates', 'rows', 'unmatchedCount', 'missingAccountCount'));
    }

    |
    | Replaces the old hardcoded column layout. Column order, headers, and
    | prefixes now come entirely from the company's BankPaymentTemplate (either
    | one of the bank presets or their own uploaded/mapped CSV — see
    | BankPaymentTemplateController). Each employee's SWIFT/BIC code is
    | auto-matched from their `bank_name` text against the `banks` reference
    | directory (Bank::findMatch) rather than requiring it to be typed in
    | per-employee; if it can't be matched, the swift_code column is left
    | blank rather than guessed.
    |
    */
    public function exportBankPaymentsCsv(Request $request, PayrollRun $run)
    {
        $run->load(['payrolls.employee']);

        $company = auth()->user()->company;

        $template = $request->filled('template_id')
            ? \App\Models\BankPaymentTemplate::where('company_id', $company->id)->findOrFail($request->get('template_id'))
            : \App\Models\BankPaymentTemplate::where('company_id', $company->id)->where('is_default', true)->first();

        if (!$template) {
            return back()->with('error', 'No bank payment template is configured yet. Set one up under Settings → Bank Payment Templates.');
        }

        $reference = $request->get('reference', strtoupper($run->period) . ' SALARY');
        $filename = "bank-bulk-payments-{$run->period}.csv";

        return response()->streamDownload(function () use ($run, $template, $reference) {

            $handle = fopen('php://output', 'w');
            $delimiter = $template->delimiter ?: ',';

            if ($template->include_header) {
                fputcsv($handle, $template->headerRow(), $delimiter);
            }

            // Resolve each employee's bank once per row — cheap enough at payroll-run volumes.
            foreach ($run->payrolls as $payroll) {
                $employee = $payroll->employee;
                if (!$employee) {
                    continue;
                }

                $matchedBank = \App\Models\Bank::findMatch($employee->bank_name ?? null);

                $row = $template->buildRow([
                    'employee' => $employee,
                    'payroll' => $payroll,
                    'run' => $run,
                    'company' => $employee->company,
                    'matchedBank' => $matchedBank,
                    'reference' => $reference,
                ]);

                fputcsv($handle, $row, $delimiter);
            }

            fclose($handle);

        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
