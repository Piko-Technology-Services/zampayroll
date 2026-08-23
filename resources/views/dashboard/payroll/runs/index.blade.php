@extends('layouts.app')

@section('content')

{{-- =====================================================================
     PAYROLL RUNS — ZamPayroll
     Toolbar (search + status filter) → Year/Month drill-down chips →
     compact rectangular tiles (short, wide, scannable at a glance).
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
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
    gap: .75rem;
}
.pr-header h1 { font-size: 1.4rem; font-weight: 700; letter-spacing: -.02em; margin: 0; color: #0F172A; }
.pr-header .subtitle { font-size: .82rem; color: #94A3B8; margin-top: .15rem; }

.pr-header-actions { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }

.pr-shortcut {
    display: inline-flex; align-items: center; gap: .4rem;
    background: #fff; border: 1px solid #E5E9F0; color: #475569;
    font-weight: 600; font-size: .8rem; padding: .55rem .95rem;
    border-radius: 10px; text-decoration: none;
    transition: border-color .15s ease, color .15s ease;
}
.pr-shortcut:hover { border-color: #CBD5E1; color: #0F172A; }
.pr-shortcut-count { font-size: .68rem; font-weight: 700; background: #F1F5F9; color: #64748B; padding: .1rem .45rem; border-radius: 20px; }
.pr-shortcut.has-items .pr-shortcut-count { background: #FFF7ED; color: #D97706; }

.pr-new-btn {
    display: inline-flex; align-items: center; gap: .45rem;
    background: #00742D; color: #fff; font-weight: 600; font-size: .85rem;
    padding: .65rem 1.15rem; border-radius: 10px; text-decoration: none;
    transition: background .18s ease, transform .18s ease;
}
.pr-new-btn:hover { background: #00611F; color: #fff; transform: translateY(-1px); }

.pr-alert-success {
    background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0;
    border-radius: 10px; padding: .7rem 1rem; font-size: .84rem; margin-bottom: 1.2rem;
}

/* ── Toolbar: search + filters ── */
.pr-toolbar {
    display: flex; align-items: center; gap: .6rem; flex-wrap: wrap;
    background: #fff; border: 1px solid #E9EDF2; border-radius: 12px;
    padding: .7rem .85rem; margin-bottom: 1rem;
}
.pr-search-field {
    position: relative; flex: 1 1 240px; min-width: 200px;
}
.pr-search-field i {
    position: absolute; left: .75rem; top: 50%; transform: translateY(-50%);
    color: #94A3B8; font-size: .85rem;
}
.pr-search-field input {
    width: 100%; font-size: .84rem; padding: .55rem .7rem .55rem 2.1rem;
    border: 1px solid #E5E9F0; border-radius: 8px; outline: none; color: #0F172A;
}
.pr-search-field input:focus { border-color: #00742D; }

.pr-filter-select {
    font-size: .82rem; padding: .55rem .7rem; border: 1px solid #E5E9F0;
    border-radius: 8px; background: #fff; color: #475569; outline: none; cursor: pointer;
}
.pr-filter-select:focus { border-color: #00742D; }

.pr-toolbar-btn {
    font-size: .82rem; font-weight: 600; padding: .55rem .95rem;
    border-radius: 8px; border: 1px solid #E5E9F0; background: #F8FAFC; color: #475569;
    cursor: pointer;
}
.pr-toolbar-btn:hover { border-color: #CBD5E1; color: #0F172A; }
.pr-toolbar-reset { font-size: .78rem; color: #94A3B8; text-decoration: none; margin-left: .2rem; }
.pr-toolbar-reset:hover { color: #F43F5E; }

/* ── Year / Month drill-down ── */
.pr-drilldown { margin-bottom: 1.25rem; }
.pr-chip-row { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
.pr-chip-row + .pr-chip-row { margin-top: .5rem; }
.pr-chip-label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #94A3B8; margin-right: .3rem; }

.pr-chip {
    display: inline-flex; align-items: center; gap: .35rem;
    font-size: .78rem; font-weight: 600; color: #64748B;
    background: #fff; border: 1px solid #E5E9F0; border-radius: 20px;
    padding: .35rem .8rem; text-decoration: none;
    transition: border-color .15s ease, color .15s ease, background .15s ease;
}
.pr-chip:hover { border-color: #00742D; color: #00742D; }
.pr-chip.active { background: #00742D; border-color: #00742D; color: #fff; }
.pr-chip .count { font-size: .68rem; opacity: .75; }
.pr-chip.disabled { opacity: .4; pointer-events: none; }

/* ── Compact rectangular tiles ── */
.pr-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: .85rem;
}

.pr-tile {
    background: #fff;
    border: 1px solid #E9EDF2;
    border-radius: 14px;
    padding: .9rem 1rem;
    cursor: pointer;
    transition: box-shadow .18s ease, border-color .18s ease, transform .18s ease;
}
.pr-tile:hover {
    box-shadow: 0 8px 20px rgba(15, 23, 42, .08);
    border-color: #D7DEE8;
    transform: translateY(-2px);
}

.pr-tile-top { display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; }
.pr-tile-alias { display: flex; align-items: center; gap: .4rem; font-size: .92rem; font-weight: 700; color: #0F172A; margin: 0; }
.pr-tile-alias i { color: #00742D; font-size: .85rem; }
.pr-tile-sub { font-size: .72rem; color: #94A3B8; margin-top: .1rem; }

.status-chip {
    font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
    padding: .2rem .6rem; border-radius: 20px; border: 1px solid transparent; white-space: nowrap; flex-shrink: 0;
}
.status-chip.draft      { background: #FFF7ED; color: #D97706; border-color: #FED7AA; }
.status-chip.processing { background: #FFFBEB; color: #B45309; border-color: #FDE68A; }
.status-chip.processed  { background: #EEF2FF; color: #00742D; border-color: #C7D2FE; }
.status-chip.approved,
.status-chip.paid       { background: #ECFDF5; color: #059669; border-color: #A7F3D0; }

/* single-row financial strip */
.pr-tile-figures {
    display: flex; align-items: stretch; gap: 0;
    margin-top: .7rem;
    border-top: 1px solid #F1F5F9;
    border-bottom: 1px solid #F1F5F9;
}
.pr-tile-fig { flex: 1; padding: .5rem .1rem; text-align: center; border-right: 1px solid #F1F5F9; }
.pr-tile-fig:last-child { border-right: none; }
.pr-tile-fig-label { font-size: .62rem; color: #94A3B8; text-transform: uppercase; letter-spacing: .04em; }
.pr-tile-fig-value { font-size: .84rem; font-weight: 800; letter-spacing: -.01em; margin-top: .1rem; }
.pr-tile-fig-value.income     { color: #00742D; }
.pr-tile-fig-value.deductions { color: #F43F5E; }
.pr-tile-fig-value.net        { color: #059669; }

/* one-line audit summary + actions */
.pr-tile-footer {
    display: flex; align-items: center; justify-content: space-between;
    margin-top: .55rem; gap: .5rem;
}
.pr-tile-audit {
    font-size: .68rem; color: #94A3B8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.pr-tile-audit strong { color: #64748B; font-weight: 600; }
.pr-tile-audit.locked strong { color: #F43F5E; }

.pr-tile-actions { display: flex; align-items: center; gap: .15rem; flex-shrink: 0; }
.pr-tile-actions a, .pr-tile-actions button {
    color: #94A3B8; font-size: .9rem; width: 26px; height: 26px; border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; background: transparent; border: none; cursor: pointer;
    transition: background .15s ease, color .15s ease;
}
.pr-tile-actions a:hover { background: #F1F5F9; color: #00742D; }
.pr-tile-actions button.hide-btn:hover { background: #F1F5F9; color: #B45309; }
.pr-tile-actions button.trash-btn:hover { background: #FFF1F2; color: #F43F5E; }
.pr-tile-actions form { margin: 0; }

/* Empty state */
.pr-empty {
    background: #fff; border: 1px dashed #D7DEE8; border-radius: 16px;
    text-align: center; padding: 3rem 1.5rem; color: #94A3B8;
}
.pr-empty i { font-size: 2rem; display: block; margin-bottom: .75rem; color: #CBD5E1; }
.pr-empty a { display: inline-block; margin-top: 1rem; font-weight: 600; color: #00742D; text-decoration: none; }
.pr-empty a:hover { text-decoration: underline; }

@media (max-width: 600px) {
    .pr-grid { grid-template-columns: 1fr; }
}
</style>

<div class="pr">

    {{-- ===== HEADER ===== --}}
    <div class="pr-header">
        <div>
            <h1>Payroll Runs</h1>
            <div class="subtitle">Monthly payroll processing cycles</div>
        </div>

        <div class="pr-header-actions">
            <a href="{{ route('payroll.runs.hidden') }}" class="pr-shortcut {{ $hiddenCount > 0 ? 'has-items' : '' }}">
                <i class="bi bi-eye-slash"></i> Hidden
                <span class="pr-shortcut-count">{{ $hiddenCount }}</span>
            </a>
            <a href="{{ route('payroll.runs.trash') }}" class="pr-shortcut {{ $trashedCount > 0 ? 'has-items' : '' }}">
                <i class="bi bi-trash3"></i> Trash
                <span class="pr-shortcut-count">{{ $trashedCount }}</span>
            </a>
            <a href="{{ route('payroll.runs.create') }}" class="pr-new-btn">
                <i class="bi bi-plus-circle"></i> New Run
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="pr-alert-success"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
    @endif

    {{-- ===== SEARCH + FILTER TOOLBAR ===== --}}
    <form method="GET" action="{{ route('payroll.runs.index') }}" class="pr-toolbar">
        {{-- preserve the active year/month drill-down when filtering --}}
        @if($year) <input type="hidden" name="year" value="{{ $year }}"> @endif
        @if($month) <input type="hidden" name="month" value="{{ $month }}"> @endif

        <div class="pr-search-field">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="{{ $search }}" placeholder="Search by alias, period, or run ID…">
        </div>

        <select name="status" class="pr-filter-select" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            @foreach($statusOptions as $opt)
                <option value="{{ $opt }}" {{ $status === $opt ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>

        <button type="submit" class="pr-toolbar-btn"><i class="bi bi-filter"></i> Apply</button>

        @if($search || $status || $year || $month)
            <a href="{{ route('payroll.runs.index') }}" class="pr-toolbar-reset">Clear all</a>
        @endif
    </form>

    {{-- ===== YEAR → MONTH DRILL-DOWN (by pay period) ===== --}}
    <div class="pr-drilldown">
        <div class="pr-chip-row">
            <span class="pr-chip-label">Pay Year</span>
            @foreach($availableYears as $y)
                <a href="{{ route('payroll.runs.index', array_merge(request()->except(['year','month','page']), ['year' => $y])) }}"
                   class="pr-chip {{ (int)$activeYear === (int)$y ? 'active' : '' }}">
                    {{ $y }}
                </a>
            @endforeach
            @if($year)
                <a href="{{ route('payroll.runs.index', request()->except(['year','month','page'])) }}" class="pr-chip">
                    <i class="bi bi-x"></i> All years
                </a>
            @endif
        </div>

        <div class="pr-chip-row">
            <span class="pr-chip-label">Month — {{ $activeYear }}</span>
            @foreach(range(1, 12) as $m)
                @php $mCount = $monthCounts[$m] ?? 0; @endphp
                <a href="{{ route('payroll.runs.index', array_merge(request()->except(['month','page']), ['year' => $activeYear, 'month' => $m])) }}"
                   class="pr-chip {{ (int)$month === $m ? 'active' : '' }} {{ $mCount === 0 ? 'disabled' : '' }}">
                    {{ \Carbon\Carbon::create()->month($m)->format('M') }}
                    <span class="count">{{ $mCount }}</span>
                </a>
            @endforeach
            @if($month)
                <a href="{{ route('payroll.runs.index', array_merge(request()->except(['month','page']), ['year' => $activeYear])) }}" class="pr-chip">
                    <i class="bi bi-x"></i> All months
                </a>
            @endif
        </div>

        @if($unparsedCount > 0)
            <div class="pr-chip-row">
                <span class="pr-chip-label" style="color:#D97706;">
                    <i class="bi bi-exclamation-triangle"></i> {{ $unparsedCount }} run(s) have a period we couldn't read as a date — they're excluded from the year/month filters until fixed.
                </span>
            </div>
        @endif
    </div>

    {{-- ===== TILE GRID ===== --}}
    @if($runs->isEmpty())
        <div class="pr-empty">
            <i class="bi bi-calendar3"></i>
            No payroll runs match these filters.
            <a href="{{ route('payroll.runs.index') }}">Clear filters</a>
        </div>
    @else
        <div class="pr-grid">
            @foreach($runs as $run)
                @php $statusKey = strtolower($run->status); @endphp

                <div class="pr-tile" onclick="window.location='{{ route('payroll.runs.show', $run->id) }}'">

                    <div class="pr-tile-top">
                        <div>
                            <h5 class="pr-tile-alias"><i class="bi bi-calendar3"></i>{{ $run->alias ?: 'Untitled run' }}</h5>
                            <div class="pr-tile-sub">
                                {{ $run->period_date?->format('F Y') ?? $run->period }}
                                @if(!$run->period_date) <span title="Couldn't parse this period as a date" style="color:#D97706;">⚠</span> @endif
                                · ID #{{ $run->id }}
                            </div>
                        </div>
                        <span class="status-chip {{ $statusKey }}">{{ ucfirst($run->status) }}</span>
                    </div>

                    <div class="pr-tile-figures">
                        <div class="pr-tile-fig">
                            <div class="pr-tile-fig-label">Income</div>
                            <div class="pr-tile-fig-value income">K{{ number_format($run->total_income, 0) }}</div>
                        </div>
                        <div class="pr-tile-fig">
                            <div class="pr-tile-fig-label">Deductions</div>
                            <div class="pr-tile-fig-value deductions">K{{ number_format($run->total_deductions, 0) }}</div>
                        </div>
                        <div class="pr-tile-fig">
                            <div class="pr-tile-fig-label">Net Pay</div>
                            <div class="pr-tile-fig-value net">K{{ number_format($run->net_pay, 0) }}</div>
                        </div>
                    </div>

                    <div class="pr-tile-footer">
                        <div class="pr-tile-audit {{ $run->finalized_at ? 'locked' : '' }}" title="Created by {{ $run->creator?->name ?? 'System' }}{{ $run->audited_by ? ', audited by '.$run->auditedBy?->name : '' }}">
                            @if($run->finalized_at)
                                <i class="bi bi-lock-fill"></i> Finalized by <strong>{{ $run->finalizedBy?->name }}</strong>
                            @else
                                By <strong>{{ $run->creator?->name ?? 'System' }}</strong>
                                @if($run->audited_by)
                                    · Audited by <strong>{{ $run->auditedBy?->name }}</strong>
                                @endif
                            @endif
                        </div>

                        <div class="pr-tile-actions" onclick="event.stopPropagation()">
                            <a href="{{ route('payroll.runs.show', $run->id) }}" title="Open">
                                <i class="bi bi-folder2-open"></i>
                            </a>

                            <form method="POST" action="{{ route('payroll.runs.hide', $run->id) }}">
                                @csrf
                                <button type="submit" class="hide-btn" title="Hide">
                                    <i class="bi bi-eye-slash"></i>
                                </button>
                            </form>

                            <form method="POST" action="{{ route('payroll.runs.trash.store', $run->id) }}"
                                  onsubmit="return confirm('Move this payroll run to trash? You can restore it later from the Trash view.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="trash-btn" title="Move to Trash">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            @endforeach
        </div>
    @endif

</div>

@endsection
