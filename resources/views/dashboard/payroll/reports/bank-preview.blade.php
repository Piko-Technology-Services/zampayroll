@extends('layouts.app')

@section('content')

<div class="bpv p-5">

    <div class="bpv-header">
        <a href="{{ route('payroll.reports.index', ['run_id' => $run->id]) }}" class="bpv-back"><i class="bi bi-arrow-left"></i> Reports</a>
        <div class="bpv-eyebrow">Banking &amp; Payments</div>
        <h1 class="bpv-title">Bank Bulk Payment — Pre-Export Check</h1>
        <div class="bpv-subtitle">{{ $run->alias }} · {{ $run->period }}</div>
    </div>

    @if($unmatchedCount > 0 || $missingAccountCount > 0)
        <div class="bpv-alert bpv-alert-warn">
            <i class="bi bi-exclamation-triangle"></i>
            @if($unmatchedCount > 0) {{ $unmatchedCount }} employee(s) have a bank we couldn't match — their SWIFT/BIC code will be blank. @endif
            @if($missingAccountCount > 0) {{ $missingAccountCount }} employee(s) have no bank account number on file. @endif
            Review before sending this file to your bank.
        </div>
    @else
        <div class="bpv-alert bpv-alert-ok"><i class="bi bi-check-circle"></i> Every employee's bank was matched and has an account number on file.</div>
    @endif

    <div class="bpv-card">
        <table class="bpv-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Bank (as entered)</th>
                    <th>Matched Bank</th>
                    <th>SWIFT/BIC</th>
                    <th>Account No.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    @php $emp = $row['employee']; @endphp
                    <tr class="{{ (!$row['matched_bank'] || !$row['has_account_number']) ? 'bpv-row-warn' : '' }}">
                        <td>{{ $emp->first_name ?? '' }} {{ $emp->last_name ?? '' }}</td>
                        <td>{{ $emp->bank_name ?? '—' }}</td>
                        <td>
                            @if($row['matched_bank'])
                                <span class="bpv-ok"><i class="bi bi-check-circle"></i> {{ $row['matched_bank']->name }}</span>
                            @else
                                <span class="bpv-warn"><i class="bi bi-exclamation-triangle"></i> Not matched</span>
                            @endif
                        </td>
                        <td class="bpv-mono">{{ $row['matched_bank']->swift_code ?? '—' }}</td>
                        <td>
                            @if($row['has_account_number'])
                                <span class="bpv-ok">{{ $emp->bank_account_no }}</span>
                            @else
                                <span class="bpv-warn"><i class="bi bi-exclamation-triangle"></i> Missing</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bpv-card">
        <div class="bpv-section-label">Export With Template</div>
        @if($templates->isEmpty())
            <div class="bpv-empty">
                No bank payment template configured yet.
                <a href="{{ route('bank-templates.index') }}">Set one up under Company Settings →</a>
            </div>
        @else
            <form method="GET" action="{{ route('payroll.reports.bank_payments.csv', $run->id) }}" class="bpv-export-form">
                <select name="template_id" class="bpv-select">
                    @foreach($templates as $t)
                        <option value="{{ $t->id }}" {{ $t->is_default ? 'selected' : '' }}>
                            {{ $t->name }} {{ $t->is_default ? '(Default)' : '' }}
                        </option>
                    @endforeach
                </select>
                <button class="bpv-btn bpv-btn-primary"><i class="bi bi-download"></i> Download Bulk Payment CSV</button>
            </form>
        @endif
    </div>

</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap');
.bpv * { box-sizing: border-box; }
.bpv {
    --ink: #00742E; --ink-soft: #58687D; --ink-faint: #93A0B2; --line: #E5E9F0;
    --ok-bg: #EAF3EC; --ok: #2F6F4E; --warn-bg: #F7E7E2; --warn: #D50115;
    font-family: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--ink); background: #fff; padding: 1.5rem 0 2.5rem; max-width: 960px; margin: 0 auto;
}
.bpv-back { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 600; color: var(--ink-soft); text-decoration: none; margin-bottom: .6rem; }
.bpv-eyebrow { font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--ink); margin-bottom: .2rem; }
.bpv-title { font-size: 1.3rem; font-weight: 700; margin: 0; }
.bpv-subtitle { font-size: .8rem; color: var(--ink-faint); margin-top: .2rem; }

.bpv-alert { border-radius: 6px; font-size: .84rem; padding: .7rem 1rem; margin: 1rem 0; }
.bpv-alert-ok { background: var(--ok-bg); color: var(--ok); border: 1px solid #C7E0CD; }
.bpv-alert-warn { background: var(--warn-bg); color: var(--warn); border: 1px solid #E3C6BC; }

.bpv-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 1.2rem 1.4rem; margin: 1rem 0; }
.bpv-section-label { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .8rem; }

.bpv-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.bpv-table thead th { font-size: .64rem; font-weight: 600; color: var(--ink-faint); text-transform: uppercase; letter-spacing: .06em; padding: .6rem .5rem; border-bottom: 2px solid var(--ink); text-align: left; }
.bpv-table td { padding: .55rem .5rem; border-bottom: 1px solid var(--line); }
.bpv-row-warn { background: #FFFBF5; }
.bpv-ok { color: var(--ok); font-weight: 600; }
.bpv-warn { color: var(--warn); font-weight: 600; }
.bpv-mono { font-family: 'IBM Plex Mono', monospace; }
.bpv-empty { color: var(--ink-faint); font-size: .84rem; }
.bpv-empty a { color: var(--ink); font-weight: 600; }

.bpv-export-form { display: flex; align-items: center; gap: .7rem; flex-wrap: wrap; }
.bpv-select { font-size: .82rem; padding: .5rem .7rem; border: 1px solid var(--line); border-radius: 5px; }
.bpv-btn { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600; padding: .55rem 1rem; border-radius: 6px; border: 1px solid transparent; cursor: pointer; text-decoration: none; }
.bpv-btn-primary { background: var(--ink); color: #fff; }
</style>

@endsection
