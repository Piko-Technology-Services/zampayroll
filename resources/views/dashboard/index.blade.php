@extends('layouts.app')

@section('content')

{{-- =====================================================================
     DASHBOARD — ZamPayroll
     Redesign: minimal light cards, thin borders, single accent color,
     hover-reactive surfaces, sparkline payroll trend, anomalies panel.
====================================================================== --}}

<style>
.hrd * { box-sizing: border-box; }

.hrd {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
    color: #0F172A;
    background: #F8FAFC;
    padding: 1.5rem 0 2.5rem;
}

/* ── Header ── */
.hrd-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: .75rem;
}
.hrd-header h1 {
    font-size: 1.4rem;
    font-weight: 700;
    letter-spacing: -.02em;
    margin: 0;
    color: #0F172A;
}
.hrd-header .subtitle {
    font-size: .82rem;
    color: #94A3B8;
    margin-top: .15rem;
}
.hrd-timestamp {
    font-size: .75rem;
    color: #94A3B8;
    background: #fff;
    border: 1px solid #E5E9F0;
    padding: .4rem .85rem;
    border-radius: 20px;
}

/* ── Base card ── */
.hrd-card {
    background: #fff;
    border: 1px solid #E9EDF2;
    border-radius: 16px;
    padding: 1.35rem 1.5rem;
    transition: box-shadow .2s ease, border-color .2s ease, transform .2s ease;
}
.hrd-card:hover {
    box-shadow: 0 8px 24px rgba(15, 23, 42, .07);
    border-color: #D7DEE8;
    transform: translateY(-2px);
}

/* ── Stat row (top 4 cards) ── */
.stat-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}
@media (max-width: 900px) { .stat-row { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 540px) { .stat-row { grid-template-columns: 1fr; } }

.stat-label {
    font-size: .74rem;
    font-weight: 600;
    color: #94A3B8;
    margin-bottom: .5rem;
}
.stat-value {
    font-size: 1.55rem;
    font-weight: 800;
    letter-spacing: -.02em;
    color: #0F172A;
    line-height: 1.15;
}
.stat-sub {
    font-size: .74rem;
    margin-top: .35rem;
    color: #94A3B8;
}
.stat-sub.good { color: #10B981; font-weight: 600; }
.stat-sub.warn { color: #D97706; font-weight: 600; }

.compliance-card { display: flex; flex-direction: column; }
.compliance-value {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: 1.15rem;
    font-weight: 800;
}
.compliance-value.ok   { color: #10B981; }
.compliance-value.warn { color: #F43F5E; }
.compliance-dot {
    width: 22px; height: 22px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .8rem;
    flex-shrink: 0;
}
.compliance-dot.ok   { background: #ECFDF5; color: #10B981; }
.compliance-dot.warn { background: #FFF1F2; color: #F43F5E; }

/* ── Main row: chart + anomalies ── */
.main-row {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}
@media (max-width: 900px) { .main-row { grid-template-columns: 1fr; } }

.panel-title {
    font-size: .88rem;
    font-weight: 700;
    color: #0F172A;
    margin: 0 0 1rem;
}
.panel-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: .75rem;
}
.panel-link {
    font-size: .74rem;
    font-weight: 600;
    color: #00742D;
    text-decoration: none;
}
.panel-link:hover { text-decoration: underline; }

/* Sparkline chart */
.chart-wrap { width: 100%; }
.chart-empty {
    text-align: center;
    color: #94A3B8;
    font-size: .85rem;
    padding: 2.25rem 0;
}
.chart-labels {
    display: flex;
    justify-content: space-between;
    font-size: .68rem;
    color: #B4BECC;
    margin-top: .4rem;
}
.chart-point {
    transition: r .15s ease;
}
.chart-point:hover { r: 6; }

/* Anomalies panel */
.anomaly-count-row {
    display: flex;
    align-items: center;
    gap: .65rem;
    margin-bottom: 1rem;
}
.anomaly-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    background: #FFF1F2;
    color: #F43F5E;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.anomaly-icon.clear { background: #ECFDF5; color: #10B981; }
.anomaly-count {
    font-size: 1.6rem;
    font-weight: 800;
    color: #0F172A;
    line-height: 1;
}
.anomaly-list { list-style: none; margin: 0 0 1rem; padding: 0; }
.anomaly-list li {
    font-size: .8rem;
    color: #475569;
    padding: .5rem 0;
    border-bottom: 1px solid #F1F5F9;
}
.anomaly-list li:last-child { border-bottom: none; }
.anomaly-badge {
    display: inline-block;
    font-size: .64rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    padding: .12rem .5rem;
    border-radius: 20px;
    margin-right: .5rem;
    vertical-align: middle;
}
.anomaly-badge.danger  { background: #FFF1F2; color: #F43F5E; }
.anomaly-badge.warning { background: #FFFBEB; color: #D97706; }
.anomaly-badge.primary { background: #EEF2FF; color: #00742D; }

.review-link {
    display: block;
    text-align: right;
    font-size: .78rem;
    font-weight: 700;
    color: #00742D;
    text-decoration: none;
}
.review-link:hover { text-decoration: underline; }

/* ── Recent payroll run row ── */
.payroll-run-card { margin-bottom: 1rem; }
.payroll-run-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.1rem;
}
.payroll-run-head .period {
    font-size: .95rem;
    font-weight: 700;
    color: #0F172A;
}
.status-chip {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    padding: .25rem .75rem;
    border-radius: 20px;
    border: 1px solid transparent;
}
.status-chip.approved,
.status-chip.finalized { background: #ECFDF5; color: #059669; border-color: #A7F3D0; }
.status-chip.processed { background: #EEF2FF; color: #00742D; border-color: #C7D2FE; }
.status-chip.draft { background: #FFF7ED; color: #D97706; border-color: #FED7AA; }

.payroll-run-figures {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
@media (max-width: 700px) { .payroll-run-figures { grid-template-columns: repeat(2, 1fr); } }
.prf-label { font-size: .7rem; color: #94A3B8; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .3rem; }
.prf-value { font-size: 1.05rem; font-weight: 800; color: #0F172A; letter-spacing: -.01em; }
.prf-value.net { color: #10B981; }
.prf-value.deductions { color: #F43F5E; }

.payroll-empty {
    text-align: center;
    color: #94A3B8;
    font-size: .85rem;
    padding: 1.5rem 0;
}
.payroll-empty a { font-weight: 600; color: #00742D; display: block; margin-top: .5rem; }

/* ── Quick actions ── */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}
@media (max-width: 700px) { .quick-actions { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .quick-actions { grid-template-columns: 1fr; } }
.qa-btn {
    display: flex;
    align-items: center;
    gap: .65rem;
    padding: 1rem 1.1rem;
    text-decoration: none;
    color: #0F172A;
    font-weight: 600;
    font-size: .82rem;
}
.qa-icon {
    width: 34px; height: 34px;
    border-radius: 9px;
    background: #ECFDF5;
    color: #00742D;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

/* ── Lower grid: departments / employees ── */
.lower-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
@media (max-width: 700px) { .lower-grid { grid-template-columns: 1fr; } }

.dept-row { margin-bottom: .85rem; }
.dept-row:last-child { margin-bottom: 0; }
.dept-meta { display: flex; justify-content: space-between; font-size: .8rem; color: #334155; margin-bottom: .3rem; }
.dept-count { font-weight: 700; color: #0F172A; }
.dept-bar-track { height: 6px; background: #F1F5F9; border-radius: 99px; overflow: hidden; }
.dept-bar-fill { height: 100%; background: #00742D; border-radius: 99px; transition: width .6s ease; }

.mini-list { list-style: none; margin: 0; padding: 0; }
.mini-list li {
    display: flex;
    align-items: center;
    gap: .65rem;
    padding: .55rem 0;
    border-bottom: 1px solid #F8FAFC;
}
.mini-list li:last-child { border-bottom: none; }
.mini-avatar {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: #00742D;
    color: #fff;
    font-weight: 700;
    font-size: .75rem;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.mini-info { flex: 1; min-width: 0; }
.mini-name { font-size: .82rem; font-weight: 600; color: #0F172A; }
.mini-meta { font-size: .7rem; color: #94A3B8; }
.mini-tag {
    font-size: .68rem;
    padding: .14rem .5rem;
    border-radius: 20px;
    background: #F1F5F9;
    color: #64748B;
    font-weight: 600;
    white-space: nowrap;
}
.no-data { text-align: center; color: #94A3B8; font-size: .82rem; padding: 1.25rem 0; }
</style>

<div class="hrd">

    {{-- ===== HEADER ===== --}}
    <div class="hrd-header">
        <div>
            <h1>Dashboard</h1>
            <div class="subtitle">Workforce &amp; payroll overview · {{ now()->format('l, d F Y') }}</div>
        </div>
        <div class="hrd-timestamp">
            <i class="bi bi-arrow-clockwise me-1"></i>Updated {{ now()->format('H:i') }}
        </div>
    </div>

    {{-- ===== TOP STAT ROW ===== --}}
    <div class="stat-row">

        <div class="hrd-card">
            <div class="stat-label">Total Employees</div>
            <div class="stat-value">{{ $stats['employees'] }}</div>
            <div class="stat-sub {{ $stats['new_this_month'] > 0 ? 'good' : '' }}">
                {{ $stats['new_this_month'] > 0 ? '+'.$stats['new_this_month'].' this month' : $stats['active_employees'].' active' }}
            </div>
        </div>

        <div class="hrd-card">
            <div class="stat-label">Total Payroll Cost</div>
            <div class="stat-value">K{{ $payrollSummary ? number_format($payrollSummary['total_earnings'], 0) : '0' }}</div>
            <div class="stat-sub">{{ $payrollSummary['period'] ?? 'No run yet' }}</div>
        </div>

        <div class="hrd-card">
            <div class="stat-label">Net Pay</div>
            <div class="stat-value">K{{ $payrollSummary ? number_format($payrollSummary['net_pay'], 0) : '0' }}</div>
            <div class="stat-sub">{{ $payrollSummary['employee_count'] ?? 0 }} employees paid</div>
        </div>

        <div class="hrd-card compliance-card">
            <div class="stat-label">Compliance</div>
            <div class="compliance-value {{ $compliance['ok'] ? 'ok' : 'warn' }}">
                <span class="compliance-dot {{ $compliance['ok'] ? 'ok' : 'warn' }}">
                    <i class="bi {{ $compliance['ok'] ? 'bi-check-lg' : 'bi-exclamation-lg' }}"></i>
                </span>
                {{ $compliance['ok'] ? 'All Clear' : $compliance['count'].' Issue'.($compliance['count'] > 1 ? 's' : '') }}
            </div>
        </div>

    </div>

        {{-- ===== QUICK ACTIONS ===== --}}
    <div class="quick-actions">
        <a href="{{ route('payroll.runs.create') }}" class="hrd-card qa-btn">
            <span class="qa-icon"><i class="bi bi-cash-coin"></i></span>
            Start Payroll Run
        </a>
        <a href="{{ route('employees.create') }}" class="hrd-card qa-btn">
            <span class="qa-icon"><i class="bi bi-person-plus-fill"></i></span>
            Add Employee
        </a>
        <a href="{{ route('reports.index') }}" class="hrd-card qa-btn">
            <span class="qa-icon"><i class="bi bi-bar-chart-fill"></i></span>
            View Reports
        </a>
    </div>

        {{-- ===== RECENT PAYROLL RUN ===== --}}
    <div class="hrd-card payroll-run-card">
        <div class="panel-title-row" style="margin-bottom:.9rem;">
            <div class="panel-title" style="margin:0;">Recent Payroll Run</div>
            @if($payrollSummary)
                <span class="status-chip {{ strtolower($payrollSummary['status']) }}">{{ $payrollSummary['status'] }}</span>
            @endif
        </div>

        @if($payrollSummary)
            <div class="payroll-run-head" style="margin-bottom: .9rem;">
                <span class="period">{{ $payrollSummary['period'] }}</span>
            </div>
            <div class="payroll-run-figures">
                <div>
                    <div class="prf-label">Employees</div>
                    <div class="prf-value">{{ $payrollSummary['employee_count'] }}</div>
                </div>
                <div>
                    <div class="prf-label">Gross Pay</div>
                    <div class="prf-value">K{{ number_format($payrollSummary['total_earnings'], 2) }}</div>
                </div>
                <div>
                    <div class="prf-label">Deductions</div>
                    <div class="prf-value deductions">K{{ number_format($payrollSummary['total_deductions'], 2) }}</div>
                </div>
                <div>
                    <div class="prf-label">Net Pay</div>
                    <div class="prf-value net">K{{ number_format($payrollSummary['net_pay'], 2) }}</div>
                </div>
            </div>
            <div class="mt-3" style="font-size:.74rem; color:#94A3B8;">
                {{ $payrollSummary['finalized_at']
                    ? 'Finalized '.\Carbon\Carbon::parse($payrollSummary['finalized_at'])->format('d M Y')
                    : 'Not yet finalized' }}
            </div>
        @else
            <div class="payroll-empty">
                <i class="bi bi-receipt fs-2 d-block mb-2"></i>
                No payroll run found yet.
                <a href="{{ route('payroll.runs.create') }}">Start a payroll run →</a>
            </div>
        @endif
    </div>



    {{-- ===== MAIN ROW: Payroll trend + Anomalies ===== --}}
    <div class="main-row">

        {{-- Payroll Overview (sparkline) --}}
        <div class="hrd-card">
            <div class="panel-title-row">
                <div class="panel-title">Payroll Overview</div>
                <a href="{{ route('payroll.runs.index') }}" class="panel-link">View all runs</a>
            </div>

            @if($payrollTrend->count() >= 2)
                @php
                    $values = $payrollTrend->pluck('net_pay')->map(fn($v) => (float) $v);
                    $max = $values->max();
                    $min = $values->min();
                    $range = ($max - $min) > 0 ? ($max - $min) : 1;
                    $w = 600; $h = 170; $pad = 16;
                    $n = $values->count();
                    $points = [];
                    foreach ($values->values() as $i => $v) {
                        $x = $pad + $i * (($w - 2*$pad) / max(1, $n - 1));
                        $y = ($h - $pad) - (($v - $min) / $range) * ($h - 2*$pad);
                        $points[] = [round($x, 1), round($y, 1)];
                    }
                    $polyline = collect($points)->map(fn($p) => "{$p[0]},{$p[1]}")->implode(' ');
                    $areaPoints = $polyline . " {$points[count($points)-1][0]},{$h} {$points[0][0]},{$h}";
                @endphp
                <div class="chart-wrap">
                    <svg viewBox="0 0 {{ $w }} {{ $h }}" width="100%" height="170" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="payrollFade" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#00742D" stop-opacity="0.18"/>
                                <stop offset="100%" stop-color="#00742D" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <polygon points="{{ $areaPoints }}" fill="url(#payrollFade)"></polygon>
                        <polyline points="{{ $polyline }}" fill="none" stroke="#00742D" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></polyline>
                        @foreach($points as $p)
                            <circle class="chart-point" cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="4" fill="#00742D"></circle>
                        @endforeach
                    </svg>
                    <div class="chart-labels">
                        @foreach($payrollTrend as $t)
                            <span>{{ $t['label'] }}</span>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="chart-empty">
                    <i class="bi bi-graph-up fs-3 d-block mb-2"></i>
                    Not enough payroll runs yet to show a trend.
                </div>
            @endif
        </div>

        {{-- Anomalies Detected --}}
        <div class="hrd-card">
            <div class="panel-title">Anomalies Detected</div>

            <div class="anomaly-count-row">
                <div class="anomaly-icon {{ $alerts->isEmpty() ? 'clear' : '' }}">
                    <i class="bi {{ $alerts->isEmpty() ? 'bi-check-lg' : 'bi-exclamation-triangle-fill' }}"></i>
                </div>
                <div class="anomaly-count">{{ $alerts->count() }}</div>
            </div>

            @if($alerts->isEmpty())
                <div class="no-data" style="padding: .5rem 0 1rem;">No issues flagged — everything looks good.</div>
            @else
                <ul class="anomaly-list">
                    @foreach($alerts as $alert)
                        <li>
                            <span class="anomaly-badge {{ $alert['severity'] }}">{{ $alert['label'] }}</span>
                            {{ $alert['message'] }}
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('employees.index') }}" class="review-link">Review Now →</a>
            @endif
        </div>

    </div>

    {{-- ===== LOWER GRID: Departments / New Employees ===== --}}
    <div class="lower-grid mb-3">

        {{-- Recently Added Employees --}}
        <div class="hrd-card">
            <div class="panel-title-row">
                <div class="panel-title" style="margin:0;">Recently Added</div>
                <a href="{{ route('employees.index') }}" class="panel-link">View all</a>
            </div>
            @if($recentEmployees->isEmpty())
                <div class="no-data">No employees yet</div>
            @else
                <ul class="mini-list">
                    @foreach($recentEmployees as $emp)
                        <li>
                            <div class="mini-avatar">
                                {{ strtoupper(substr($emp->first_name, 0, 1)) }}{{ strtoupper(substr($emp->last_name, 0, 1)) }}
                            </div>
                            <div class="mini-info">
                                <div class="mini-name">{{ $emp->first_name }} {{ $emp->last_name }}</div>
                                <div class="mini-meta">{{ $emp->position ?? '—' }} · {{ $emp->created_at->diffForHumans() }}</div>
                            </div>
                            @if($emp->department)
                                <span class="mini-tag">{{ $emp->department }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

    </div>



</div>
@endsection