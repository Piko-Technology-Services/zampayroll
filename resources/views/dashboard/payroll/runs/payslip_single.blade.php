<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $run->period }} Payslips — {{ $companyName ?? 'ZamPayroll' }}</title>

<style>
    @page {
        size: A4;
        margin: 12mm 14mm;
    }

    * { box-sizing: border-box; }

    body {
        font-family: 'Helvetica Neue', Arial, sans-serif;
        color: #000000;
        background: #fff;
        margin: 0;
        padding: 0;
        font-size: 11.5px;
    }

    .mono { font-family: 'Courier New', Courier, monospace; }

    .print-bar {
        max-width: 900px;
        margin: 0 auto 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .print-bar h2 { font-family: Georgia, 'Times New Roman', serif; font-size: 17px; margin: 0; color: #1A2530; font-weight: 700; }
    .print-bar div { display: flex; gap: 8px; }
    .print-bar button {
        background: #fff;
        color: #1A2530;
        border: 1px solid #1A2530;
        padding: 9px 16px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }
    .print-bar button:first-child { background: #1A2530; color: #fff; }
    .print-bar button:hover { opacity: .85; }

    /* ════════════════════════════════════════════════
       PAYSLIP SHEET — formal statement layout
    ════════════════════════════════════════════════ */
    .payslip {
        max-width: 900px;
        margin: 0 auto 26px;
        border: 1px solid #C7A548;
        page-break-after: always;
        font-size: 11px;
    }
    .payslip:last-child { page-break-after: auto; }

    /* ── Letterhead ──────────────────────────────────── */
    .letterhead {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 16px 20px 12px;
        border-bottom: 2px solid #1A2530;
    }
    .lh-company { display: flex; align-items: center; gap: 10px; }
    .lh-company img { height: 36px; width: auto; }
    .lh-company-name { font-family: Georgia, 'Times New Roman', serif; font-size: 16px; font-weight: 700; color: #1A2530; }
    .lh-company-sub { font-size: 9.5px; color: #7A8595; text-transform: uppercase; letter-spacing: .06em; margin-top: 1px; }

    .lh-doc { text-align: right; }
    .lh-doc-title {
        font-family: Georgia, 'Times New Roman', serif;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: .1em;
        color: #C7A548;
        text-transform: uppercase;
    }
    .lh-doc-period { font-size: 15px; font-weight: 700; color: #1A2530; margin-top: 2px; }
    .lh-doc-ref { font-size: 9.5px; color: #7A8595; margin-top: 2px; }

    /* ── Section label (dossier numbering) ──────────── */
    .sec-label {
        display: flex;
        align-items: baseline;
        gap: 8px;
        font-family: Georgia, 'Times New Roman', serif;
        font-weight: 700;
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #1A2530;
        padding: 10px 20px 6px;
    }
    .sec-no {
        font-family: 'Courier New', Courier, monospace;
        font-size: 9px;
        font-weight: 700;
        color: #fff;
        background: #C7A548;
        padding: 1px 5px;
        border-radius: 2px;
    }

    /* ── Employee & pay details grid ────────────────── */
    .details-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 6px 0;
        padding: 0 20px 12px;
        border-bottom: 1px solid #E4DFD0;
    }
    .details-item { padding: 2px 10px 2px 0; }
    .details-label { font-size: 8.5px; text-transform: uppercase; letter-spacing: .05em; color: #7A8595; margin-bottom: 1px; }
    .details-value { font-size: 11px; color: #1A2530; font-weight: 600; }

    /* ── Earnings / Deductions tables ────────────────── */
    .tables-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        border-bottom: 1px solid #E4DFD0;
    }
    .table-col { padding: 0 0 10px; }
    .table-col + .table-col { border-left: 1px solid #E4DFD0; }

    .lines-table { width: 100%; border-collapse: collapse; }
    .lines-table thead th {
        text-align: left;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #7A8595;
        font-weight: 700;
        padding: 4px 20px 5px;
        border-bottom: 1px solid #1A2530;
    }
    .lines-table thead th.num { text-align: right; }
    .lines-table td { padding: 3px 20px; font-size: 11px; }
    .lines-table td.num {
        text-align: right;
        font-family: 'Courier New', Courier, monospace;
        white-space: nowrap;
    }
    .lines-table td.days { text-align: right; width: 60px; font-family: 'Courier New', Courier, monospace; color: #7A8595; }

    .code-cell { display: flex; gap: 6px; }
    .code-cell .code { font-family: 'Courier New', Courier, monospace; font-weight: 700; color: #C7A548; width: 30px; flex-shrink: 0; }

    .table-total-row td {
        border-top: 1px solid #1A2530;
        font-weight: 700;
        padding-top: 5px;
    }

    /* ── YTD strip ───────────────────────────────────── */
    .ytd-strip {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        padding: 10px 20px 12px;
        border-bottom: 1px solid #E4DFD0;
    }
    .ytd-item { border-left: 1px solid #E4DFD0; padding: 0 12px; }
    .ytd-item:first-child { border-left: none; padding-left: 0; }
    .ytd-label { font-size: 8.5px; text-transform: uppercase; letter-spacing: .05em; color: #7A8595; margin-bottom: 2px; }
    .ytd-value { font-family: 'Courier New', Courier, monospace; font-size: 11.5px; font-weight: 700; color: #1A2530; }

    /* ── Summary: totals + net stamp ─────────────────── */
    .summary-row {
        display: flex;
        justify-content: flex-end;
        align-items: flex-end;
        gap: 26px;
        padding: 14px 20px 16px;
    }
    .summary-lines { display: flex; flex-direction: column; gap: 4px; }
    .summary-lines .s-row { display: flex; justify-content: space-between; gap: 22px; font-size: 11px; }
    .summary-lines .s-label { color: #7A8595; }
    .summary-lines .s-value { font-family: 'Courier New', Courier, monospace; font-weight: 700; color: #1A2530; }

    .net-stamp {
        border: 2px solid #1A2530;
        border-radius: 4px;
        padding: 8px 18px;
        text-align: right;
    }
    .net-stamp-label { font-size: 9px; text-transform: uppercase; letter-spacing: .08em; color: #7A8595; margin-bottom: 2px; }
    .net-stamp-value { font-family: 'Courier New', Courier, monospace; font-size: 17px; font-weight: 700; color: #1F6B3C; }

    /* ── Footer strap ─────────────────────────────────── */
    .strap-line {
        text-align: center;
        padding: 8px 16px 10px;
        font-size: 8.5px;
        color: #A9A190;
        font-style: italic;
        border-top: 1px solid #E4DFD0;
    }

    @media print {
        .print-bar { display: none; }
        .payslip { border: 1px solid #C7A548; }
    }

    @media screen {
        body { background: #F1EFE9; padding: 24px 0; }
        .payslip { background: #fff; box-shadow: 0 1px 4px rgba(26,37,48,0.1); }
    }
</style>
</head>

<body>

    <div class="print-bar">
        <h2>{{ $run->period }} — Payslips</h2>
        <div>
            <button onclick="window.print()">Print</button>
            <button type="button" onclick="window.location.href='{{ route('payroll.pdf', $payroll) }}'">Download</button>
        </div>
    </div>

    @php
        $companyName = $companyName ?? ($run->payrolls->first()->company ?? 'ZamPayroll');
        $companyLogo = $companyLogo ?? null; // pass a public URL or base64 data URI from the controller
    @endphp

    @foreach($run->payrolls as $index => $payroll)

        @php
            $employee   = $payroll->employee;
            $earnings   = $payroll->items->where('type', 'earning')->values();
            $deductions = $payroll->items->where('type', 'deduction')->values();

            // Pad short item lists so every payslip keeps a consistent visual height
            $maxRows   = max($earnings->count(), $deductions->count(), 5);
            $fillerE   = max(0, $maxRows - $earnings->count());
            $fillerD   = max(0, $maxRows - $deductions->count());

            // YTD figures — fall back gracefully if your Payroll model doesn't carry these yet
            $ytdIncome   = $employee->ytd_income   ?? $payroll->total_income;
            $ytdTax      = $employee->ytd_tax      ?? optional($deductions->firstWhere('code', 'D00'))->amount ?? 0;
            $ytdNetForTax= $employee->ytd_net_for_tax ?? ($ytdIncome - $ytdTax);
            $ytdNapsa    = $employee->ytd_napsa    ?? optional($deductions->firstWhere('code', 'D02'))->amount ?? 0;
            $leaveDays   = $employee->leave_days_balance ?? 0;
            $leaveValue  = $employee->leave_days_value   ?? 0;

            $daysWorked  = $payroll->days_worked ?? 26.00;
        @endphp

        <div class="payslip">

            {{-- ════════════════════════════════════════
                 LETTERHEAD
            ════════════════════════════════════════ --}}
            <div class="letterhead">
                <div class="lh-company">
                    @if($companyLogo)
                        <img src="{{ $companyLogo }}" alt="{{ $companyName }} logo">
                    @endif
                    <div>
                        <div class="lh-company-name">{{ $companyName }}</div>
                        <div class="lh-company-sub">Payroll Department</div>
                    </div>
                </div>
                <div class="lh-doc">
                    <div class="lh-doc-title">Payslip</div>
                    <div class="lh-doc-period">{{ strtoupper($run->period) }}</div>
                    <div class="lh-doc-ref">Ref: {{ $employee->employee_id }}</div>
                </div>
            </div>

            {{-- ════════════════════════════════════════
                 01 — EMPLOYEE &amp; PAY DETAILS
            ════════════════════════════════════════ --}}
            <div class="sec-label"><span class="sec-no">01</span>Employee &amp; Pay Details</div>

            <div class="details-grid">
                <div class="details-item">
                    <div class="details-label">Employee Name</div>
                    <div class="details-value">{{ $employee->first_name }} {{ $employee->last_name }}</div>
                </div>
                <div class="details-item">
                    <div class="details-label">Employee ID</div>
                    <div class="details-value mono">{{ $employee->employee_id }}</div>
                </div>
                <div class="details-item">
                    <div class="details-label">Job Title</div>
                    <div class="details-value">{{ $employee->position }}</div>
                </div>
                <div class="details-item">
                    <div class="details-label">Date Engaged</div>
                    <div class="details-value mono">{{ \Carbon\Carbon::parse($employee->contract_start)->format('d/m/Y') }}</div>
                </div>

                <div class="details-item">
                    <div class="details-label">Branch</div>
                    <div class="details-value">{{ $payroll->branch }}</div>
                </div>
                <div class="details-item">
                    <div class="details-label">Cost Centre</div>
                    <div class="details-value">{{ $payroll->cost_centre }}</div>
                </div>
                <div class="details-item">
                    <div class="details-label">Salary Rate</div>
                    <div class="details-value mono">K {{ number_format($employee->salary ?? 0, 2) }}</div>
                </div>
                <div class="details-item">
                    <div class="details-label">Pay Method</div>
                    <div class="details-value">{{ $employee->pay_method ?? 'BANK TRANSFER' }}</div>
                </div>

                <div class="details-item">
                    <div class="details-label">Bank A/C No.</div>
                    <div class="details-value mono">{{ $employee->bank_account_no ?? '—' }}</div>
                </div>
                <div class="details-item">
                    <div class="details-label">NRC No.</div>
                    <div class="details-value mono">{{ $employee->nrc_no ?? '—' }}</div>
                </div>
                <div class="details-item">
                    <div class="details-label">NHIMA No.</div>
                    <div class="details-value mono">{{ $employee->nhima_no ?? '—' }}</div>
                </div>
                <div class="details-item">
                    <div class="details-label">SSN No.</div>
                    <div class="details-value mono">{{ $employee->ssn ?? '—' }}</div>
                </div>

                <div class="details-item">
                    <div class="details-label">TPIN No.</div>
                    <div class="details-value mono">{{ $employee->tpin ?? '—' }}</div>
                </div>
            </div>

            {{-- ════════════════════════════════════════
                 02/03 — EARNINGS  |  DEDUCTIONS
            ════════════════════════════════════════ --}}
            <div class="tables-row">

                <div class="table-col">
                    <div class="sec-label"><span class="sec-no">02</span>Earnings</div>
                    <table class="lines-table">
                        <thead>
                            <tr>
                                <th>Code / Description</th>
                                <th class="num">Days/Hrs</th>
                                <th class="num">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($earnings as $i => $e)
                                <tr>
                                    <td>
                                        <span class="code-cell">
                                            <span class="code">{{ $e->code }}</span>
                                            <span>{{ strtoupper($e->description) }}</span>
                                        </span>
                                    </td>
                                    <td class="days">{{ $i === 0 ? number_format($daysWorked, 2) : '' }}</td>
                                    <td class="num">{{ number_format($e->amount, 2) }}</td>
                                </tr>
                            @endforeach

                            @for($i = 0; $i < $fillerE; $i++)
                                <tr><td>&nbsp;</td><td class="days"></td><td class="num"></td></tr>
                            @endfor

                            <tr class="table-total-row">
                                <td>Total Income</td>
                                <td class="days"></td>
                                <td class="num">K {{ number_format($payroll->total_income, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="table-col">
                    <div class="sec-label"><span class="sec-no">03</span>Deductions</div>
                    <table class="lines-table">
                        <thead>
                            <tr>
                                <th>Code / Description</th>
                                <th class="num">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($deductions as $d)
                                <tr>
                                    <td>
                                        <span class="code-cell">
                                            <span class="code">{{ $d->code }}</span>
                                            <span>{{ strtoupper($d->description) }}</span>
                                        </span>
                                    </td>
                                    <td class="num">{{ number_format($d->amount, 2) }}</td>
                                </tr>
                            @endforeach

                            @for($i = 0; $i < $fillerD; $i++)
                                <tr><td>&nbsp;</td><td class="num"></td></tr>
                            @endfor

                            <tr class="table-total-row">
                                <td>Total Deductions</td>
                                <td class="num">K {{ number_format($payroll->total_deductions, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>

            {{-- ════════════════════════════════════════
                 04 — YEAR TO DATE
            ════════════════════════════════════════ --}}
            <div class="sec-label"><span class="sec-no">04</span>Year to Date</div>

            <div class="ytd-strip">
                <div class="ytd-item">
                    <div class="ytd-label">Total Income YTD</div>
                    <div class="ytd-value">K {{ number_format($ytdIncome, 2) }}</div>
                </div>
                <div class="ytd-item">
                    <div class="ytd-label">Net for Tax YTD</div>
                    <div class="ytd-value">K {{ number_format($ytdNetForTax, 2) }}</div>
                </div>
                <div class="ytd-item">
                    <div class="ytd-label">Tax YTD</div>
                    <div class="ytd-value">K {{ number_format($ytdTax, 2) }}</div>
                </div>
                <div class="ytd-item">
                    <div class="ytd-label">Napsa YTD</div>
                    <div class="ytd-value">K {{ number_format($ytdNapsa, 2) }}</div>
                </div>
                <div class="ytd-item">
                    <div class="ytd-label">Leave Days</div>
                    <div class="ytd-value">{{ number_format($leaveDays, 4) }}</div>
                </div>
            </div>

            {{-- ════════════════════════════════════════
                 05 — PAY SUMMARY
            ════════════════════════════════════════ --}}
            <div class="sec-label"><span class="sec-no">05</span>Pay Summary</div>

            <div class="summary-row">
                <div class="summary-lines">
                    <div class="s-row">
                        <span class="s-label">Total Income</span>
                        <span class="s-value">K {{ number_format($payroll->total_income, 2) }}</span>
                    </div>
                    <div class="s-row">
                        <span class="s-label">Total Deductions</span>
                        <span class="s-value">K {{ number_format($payroll->total_deductions, 2) }}</span>
                    </div>
                </div>
                <div class="net-stamp">
                    <div class="net-stamp-label">Net Pay</div>
                    <div class="net-stamp-value">K {{ number_format($payroll->net_pay, 2) }}</div>
                </div>
            </div>

            <div class="strap-line">
                Generated by ZamPayroll Internal Payroll System
            </div>

        </div>

    @endforeach

</body>
</html>