@extends('layouts.app')

@section('content')

{{-- =====================================================================
     PAYROLL RUNS — ZamPayroll
     Redesign: same light card language as the dashboard —
     thin borders, hover-reactive cards, single green accent.
====================================================================== --}}

<style>
.pr * { box-sizing: border-box; }

.pr {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
    color: #0F172A;
    background: #F8FAFC;
    padding: 1.5rem 0 2.5rem;
}

/* ── Header ── */
.pr-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: .75rem;
}
.pr-header h1 {
    font-size: 1.4rem;
    font-weight: 700;
    letter-spacing: -.02em;
    margin: 0;
    color: #0F172A;
}
.pr-header .subtitle {
    font-size: .82rem;
    color: #94A3B8;
    margin-top: .15rem;
}
.pr-new-btn {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    background: #00742D;
    color: #fff;
    font-weight: 600;
    font-size: .85rem;
    padding: .65rem 1.15rem;
    border-radius: 10px;
    text-decoration: none;
    transition: background .18s ease, transform .18s ease;
}
.pr-new-btn:hover { background: #00611F; color: #fff; transform: translateY(-1px); }

/* ── Grid ── */
.pr-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}
@media (max-width: 1100px) { .pr-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  { .pr-grid { grid-template-columns: 1fr; } }

/* ── Run card ── */
.pr-card {
    background: #fff;
    border: 1px solid #E9EDF2;
    border-radius: 16px;
    overflow: hidden;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    transition: box-shadow .2s ease, border-color .2s ease, transform .2s ease;
}
.pr-card:hover {
    box-shadow: 0 10px 26px rgba(15, 23, 42, .08);
    border-color: #D7DEE8;
    transform: translateY(-3px);
}

.pr-card-head {
    padding: 1.25rem 1.35rem 0;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .5rem;
}
.pr-alias {
    display: flex;
    align-items: center;
    gap: .45rem;
    font-size: 1rem;
    font-weight: 700;
    color: #0F172A;
    margin: 0 0 .2rem;
}
.pr-alias i { color: #00742D; font-size: .95rem; }
.pr-period { font-size: .78rem; color: #94A3B8; }
.pr-id { font-size: .7rem; color: #B4BECC; margin-top: .1rem; }

.status-chip {
    font-size: .66rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    padding: .25rem .7rem;
    border-radius: 20px;
    border: 1px solid transparent;
    white-space: nowrap;
    flex-shrink: 0;
}
.status-chip.draft      { background: #FFF7ED; color: #D97706; border-color: #FED7AA; }
.status-chip.processing { background: #FFFBEB; color: #B45309; border-color: #FDE68A; }
.status-chip.processed  { background: #EEF2FF; color: #00742D; border-color: #C7D2FE; }
.status-chip.approved,
.status-chip.paid       { background: #ECFDF5; color: #059669; border-color: #A7F3D0; }

/* Financial stats */
.pr-figures {
    padding: 1.1rem 1.35rem 0;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .6rem;
}
.pr-fig {
    background: #F8FAFC;
    border-radius: 10px;
    padding: .6rem .75rem;
}
.pr-fig-label { font-size: .68rem; color: #94A3B8; text-transform: uppercase; letter-spacing: .04em; }
.pr-fig-value { font-size: .95rem; font-weight: 800; letter-spacing: -.01em; margin-top: .15rem; }
.pr-fig-value.income      { color: #00742D; }
.pr-fig-value.deductions  { color: #F43F5E; }

.pr-net {
    margin: .6rem 1.35rem 0;
    background: #ECFDF5;
    border-radius: 10px;
    padding: .75rem .9rem;
}
.pr-net-label { font-size: .68rem; color: #059669; text-transform: uppercase; letter-spacing: .04em; font-weight: 600; }
.pr-net-value { font-size: 1.25rem; font-weight: 800; color: #059669; letter-spacing: -.02em; margin-top: .1rem; }

/* Audit trail */
.pr-audit {
    padding: 1rem 1.35rem 0;
    margin-top: .35rem;
}
.pr-audit-row {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: .74rem;
    color: #94A3B8;
    padding: .25rem 0;
}
.pr-audit-row i { width: 14px; text-align: center; flex-shrink: 0; }
.pr-audit-row strong { color: #475569; font-weight: 600; }
.pr-audit-row.locked strong { color: #F43F5E; }
.pr-audit-row.pending strong { color: #D97706; }

/* Action bar */
.pr-actions {
    margin-top: 1.1rem;
    padding: .75rem 1.1rem;
    border-top: 1px solid #F1F5F9;
    display: flex;
    align-items: center;
    justify-content: space-around;
}
.pr-actions a {
    color: #94A3B8;
    font-size: 1rem;
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none;
    transition: background .15s ease, color .15s ease;
}
.pr-actions a:hover { background: #F1F5F9; color: #00742D; }
.pr-actions a.danger:hover { background: #FFF1F2; color: #F43F5E; }

/* Empty state */
.pr-empty {
    background: #fff;
    border: 1px dashed #D7DEE8;
    border-radius: 16px;
    text-align: center;
    padding: 3rem 1.5rem;
    color: #94A3B8;
}
.pr-empty i { font-size: 2rem; display: block; margin-bottom: .75rem; color: #CBD5E1; }
.pr-empty a {
    display: inline-block;
    margin-top: 1rem;
    font-weight: 600;
    color: #00742D;
    text-decoration: none;
}
.pr-empty a:hover { text-decoration: underline; }
</style>

<div class="pr">

    {{-- ===== HEADER ===== --}}
    <div class="pr-header">
        <div>
            <h1>Payroll Runs</h1>
            <div class="subtitle">Monthly payroll processing cycles</div>
        </div>

        <a href="{{ route('payroll.runs.create') }}" class="pr-new-btn">
            <i class="bi bi-plus-circle"></i> New Run
        </a>
    </div>

    {{-- ===== GRID ===== --}}
    @if($runs->isEmpty())
        <div class="pr-empty">
            <i class="bi bi-calendar3"></i>
            No payroll runs yet.
            <a href="{{ route('payroll.runs.create') }}">Start your first payroll run →</a>
        </div>
    @else
        <div class="pr-grid">
            @foreach($runs as $run)
                @php
                    $statusKey = strtolower($run->status);
                @endphp

                <div class="pr-card" onclick="window.location='{{ route('payroll.runs.show', $run->id) }}'">

                    {{-- HEAD --}}
                    <div class="pr-card-head">
                        <div>
                            <h5 class="pr-alias"><i class="bi bi-calendar3"></i>{{ $run->alias }}</h5>
                            <div class="pr-period">{{ $run->period ?? 'No alias set' }}</div>
                            <div class="pr-id">ID #{{ $run->id }}</div>
                        </div>
                        <span class="status-chip {{ $statusKey }}">{{ ucfirst($run->status) }}</span>
                    </div>

                    {{-- FINANCIAL STATS --}}
                    <div class="pr-figures">
                        <div class="pr-fig">
                            <div class="pr-fig-label">Total Income</div>
                            <div class="pr-fig-value income">K{{ number_format($run->total_income, 2) }}</div>
                        </div>
                        <div class="pr-fig">
                            <div class="pr-fig-label">Deductions</div>
                            <div class="pr-fig-value deductions">K{{ number_format($run->total_deductions, 2) }}</div>
                        </div>
                    </div>

                    <div class="pr-net">
                        <div class="pr-net-label">Net Pay</div>
                        <div class="pr-net-value">K{{ number_format($run->net_pay, 2) }}</div>
                    </div>

                    {{-- AUDIT TRAIL --}}
                    <div class="pr-audit">
                        <div class="pr-audit-row">
                            <i class="bi bi-person-plus"></i>
                            Created by <strong>{{ $run->createdBy?->name ?? 'System' }}</strong>
                        </div>
                        <div class="pr-audit-row">
                            <i class="bi bi-pencil-square"></i>
                            Updated by <strong>{{ $run->updatedBy?->name ?? '—' }}</strong>
                            @if($run->updated_at)
                                · {{ $run->updated_at->format('d M Y H:i') }}
                            @endif
                        </div>
                        <div class="pr-audit-row">
                            <i class="bi bi-check2-circle"></i>
                            Audited by <strong>{{ $run->auditedBy?->name ?? 'Not audited' }}</strong>
                        </div>
                        <div class="pr-audit-row {{ $run->finalized_at ? 'locked' : 'pending' }}">
                            @if($run->finalized_at)
                                <i class="bi bi-lock-fill"></i>
                                Finalized by <strong>{{ $run->finalizedBy?->name }}</strong>
                            @else
                                <i class="bi bi-exclamation-circle"></i>
                                <strong>Draft run</strong>
                            @endif
                        </div>
                    </div>

                    {{-- ACTION BAR --}}
                    <div class="pr-actions" onclick="event.stopPropagation()">
                        <a href="{{ route('payroll.runs.show', $run->id) }}" title="Open">
                            <i class="bi bi-folder2-open"></i>
                        </a>
                        <a href="{{ route('payroll.runs.generate', $run->id) }}" title="Generate">
                            <i class="bi bi-gear-wide-connected"></i>
                        </a>
                        <a href="#" title="Reports">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                        </a>
                        <a href="#" title="Backup">
                            <i class="bi bi-cloud-arrow-down"></i>
                        </a>
                        <a href="#" title="Share">
                            <i class="bi bi-share"></i>
                        </a>
                        <a href="#" class="danger" title="Delete">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>

                </div>
            @endforeach
        </div>
    @endif

</div>

@endsection