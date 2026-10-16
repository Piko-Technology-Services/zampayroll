@extends('layouts.app')

@section('title', $run->displayName() . ' · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
@endpush

@php
    $k = fn ($n) => 'K ' . number_format((float) $n, 2);
    $fixes = $insights['counts']['fix'];
    $firstStep = $steps->first();
    $delta = function ($now, $before) {
        if ($before === null || (float) $before == 0.0) return null;
        $d = (float) $now - (float) $before;
        if (abs($d) < 0.005) return ['same', 'same as last month'];
        return [$d > 0 ? 'up' : 'down', ($d > 0 ? '▲ ' : '▼ ') . 'K ' . number_format(abs($d), 2)];
    };
    $kpis = [
        ['People', $totals['people'], $prevTotals['people'] ?? null, false],
        ['Gross pay', $totals['total_income'], $prevTotals['total_income'] ?? null, true],
        ['PAYE (ZRA)', $totals['paye'], $prevTotals['paye'] ?? null, true],
        ['NAPSA', $totals['napsa'], $prevTotals['napsa'] ?? null, true],
        ['NHIMA', $totals['nhima'], $prevTotals['nhima'] ?? null, true],
        ['Take-home', $totals['net_pay'], $prevTotals['net_pay'] ?? null, true],
    ];
    $lastLock = $run->events->where('action', 'locked')->last();
    $drawerRows = $rows->map(fn ($r) => [
        'pid' => $r['payroll']->id,
        'eid' => $r['employee']?->id,
        'name' => $r['employee']?->full_name ?? 'Unknown',
        'role' => trim(($r['employee']?->position ?? '') . ($r['employee']?->department ? ' · ' . $r['employee']->department : '')),
        'ini' => $r['employee']?->initials ?? '?',
        'photo' => $r['employee']?->photo_url,
        'gross' => $r['gross'], 'net' => $r['net'], 'prev' => $r['prev'],
        'earn' => $r['earnings']->map(fn ($i) => ['d' => $i->description, 'a' => (float) $i->amount])->values(),
        'ded' => $r['deductions']->map(fn ($i) => ['d' => $i->description, 'a' => (float) $i->amount])->values(),
        'flags' => collect($r['flags'])->map(fn ($f) => ['s' => $f['severity'], 'i' => $f['icon'], 't' => $f['title'], 'd' => $f['detail']])->values(),
        'slip' => route('payroll.runs.employee.summary', [$run, $r['payroll']]),
        'profile' => $r['employee'] ? route('employees.show', $r['employee']) : null,
    ])->keyBy('pid');
@endphp

@section('content')
<div class="pr pp" id="prRun" data-adjust="{{ route('payroll.runs.adjustments.store', $run) }}">
    <a class="pr-crumb" href="{{ route('payroll.runs.index') }}"><i class="bi bi-arrow-left"></i> Payroll</a>
    <div class="pr-head">
        <div>
            <h1>{{ $run->displayName() }} <span class="pr-pill {{ $run->statusTone() }}" style="vertical-align:middle;font-family:var(--zp-font)">{{ $run->statusLabel() }}</span></h1>
            <p>
                {{ $totals['people'] }} people
                @if($run->creator) · started by {{ $run->creator->name }}@endif
                @if($ev = $run->events->where('action', 'calculated')->last()) · calculated {{ $ev->created_at->diffForHumans() }}@endif
            </p>
        </div>
        <div class="pr-head-actions">
            @if($canEdit)
                <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('payroll.runs.editor', $run) }}" title="Line-by-line editor, bulk adjustments and imports"><i class="bi bi-sliders"></i> Advanced editor</a>
            @endif
            <button type="button" class="pp-btn pp-btn-dark pp-btn-sm" data-ai-prompt="Explain the {{ $run->period }} payroll: totals, biggest changes and anything unusual."><i class="bi bi-stars"></i> Ask AI</button>
        </div>
    </div>

    <div class="pr-card" style="padding:1.1rem 1.2rem .9rem">@include('dashboard.payroll.flow._track', ['stage' => $stage])</div>

    {{-- ================= NEXT STEP ================= --}}
    @if($run->isEditable())
        <section class="pr-next">
            <div class="pr-next-row">
                <div>
                    <div class="eyebrow">Step 2 of 5 · Check</div>
                    <h2>{{ $fixes ? $fixes . ' ' . ($fixes === 1 ? 'thing needs' : 'things need') . ' fixing' : 'Looks good — ready for approval' }}</h2>
                    <p>{{ $fixes ? 'Fix them on the employee profile and press Recalculate — or submit anyway if you know why.' : 'Glance through the highlights and the people list below, then send it for approval.' }}</p>
                </div>
                @if($canPrepare)
                    <div class="pr-next-actions">
                        <form method="POST" action="{{ route('payroll.runs.generate', $run) }}">@csrf
                            <button class="pp-btn pp-btn-ghost" data-busy="Recalculating…"><i class="bi bi-arrow-repeat"></i> <span>Recalculate</span></button>
                        </form>
                    </div>
                @endif
            </div>
            @if($canPrepare)
                <form method="POST" action="{{ route('payroll.runs.submit', $run) }}" class="pr-submit-panel">
                    @csrf
                    <div>
                        <textarea name="comment" placeholder="Note for the approvers (optional) — e.g. “Includes Chanda's backpay”">{{ old('comment') }}</textarea>
                        @if($fixes)
                            <label class="pr-ack"><input type="checkbox" name="acknowledged" value="1" required> I've looked at the {{ $fixes }} item(s) that need fixing and want to submit anyway.</label>
                        @endif
                    </div>
                    <button class="pp-btn pp-btn-light" style="padding:1rem 1.3rem" data-busy="Submitting…"><i class="bi bi-send"></i> <span>Submit for approval</span></button>
                </form>
                <p style="font-size:.78rem;margin-top:.6rem;color:rgba(255,255,255,.65)">Goes to <b>{{ $firstStep['name'] }}</b> ({{ $firstStep['who'] }}){{ $steps->count() > 1 ? ', then ' . $steps->slice(1)->pluck('name')->implode(', then ') : '' }}. Changes are frozen while it's in approval.</p>
            @endif
        </section>
    @elseif($run->isInReview())
        <section class="pr-next review">
            @if($canApprove)
                <div class="eyebrow">Your approval · {{ $current['name'] }}</div>
                <h2>Review and approve {{ $run->displayName() }}</h2>
                <p>Submitted by {{ $run->submitter?->name ?? 'HR' }} {{ optional($run->submitted_at)->diffForHumans() }}.
                    @php $note = $run->events->where('action', 'submitted')->last()?->comment; @endphp
                    @if($note)<br>“{{ $note }}”@endif
                </p>
                <form method="POST" action="{{ route('payroll.runs.approve', $run) }}" class="pr-submit-panel" id="prDecide">
                    @csrf
                    <textarea name="comment" placeholder="Comment (required if you send it back)">{{ old('comment') }}</textarea>
                    <div class="pr-next-actions">
                        <button class="pp-btn pp-btn-ghost" formaction="{{ route('payroll.runs.sendBack', $run) }}" data-needs-comment><i class="bi bi-arrow-return-left"></i> Send back</button>
                        <button class="pp-btn pp-btn-light" data-busy="Approving…"><i class="bi bi-check2-circle"></i> <span>{{ $run->current_step >= $steps->count() ? 'Approve & lock' : 'Approve' }}</span></button>
                    </div>
                </form>
            @else
                <div class="pr-next-row">
                    <div>
                        <div class="eyebrow">Step 3 of 5 · Approval {{ $run->current_step }} of {{ $steps->count() }}</div>
                        <h2>Waiting for {{ $current['name'] ?? 'approval' }}</h2>
                        <p>{{ $steps->firstWhere('state', 'current')['who'] ?? '' }}@if(($ppl = $steps->firstWhere('state', 'current')['people'] ?? collect())->isNotEmpty()) — {{ $ppl->implode(', ') }}@endif. We've emailed them.
                            @if($whyNot && str_contains($whyNot, 'prepared'))<br>{{ $whyNot }}@endif
                        </p>
                    </div>
                    @if($canPrepare)
                        <form method="POST" action="{{ route('payroll.runs.recall', $run) }}" onsubmit="event.stopPropagation(); return confirm('Pull this payroll back to draft? Approvals so far will need to be given again.')">@csrf
                            <button class="pp-btn pp-btn-ghost"><i class="bi bi-arrow-counterclockwise"></i> Recall to make changes</button>
                        </form>
                    @endif
                </div>
            @endif
        </section>
    @else
        <section class="pr-next locked">
            <div class="pr-next-row">
                <div>
                    <div class="eyebrow">{{ $run->status === \App\Models\PayrollRun::PAID ? 'Step 5 of 5 · Paid' : 'Step 4 of 5 · Locked' }}</div>
                    <h2>{{ $run->status === \App\Models\PayrollRun::PAID ? 'Paid ' . optional($run->paid_at)->format('j M Y') . ' 🎉' : 'Approved and locked 🔒' }}</h2>
                    <p>Approved {{ optional($run->finalized_at)->format('j M Y, H:i') }}{{ $run->finalizedBy ? ' by ' . $run->finalizedBy->name : '' }}. Figures can't change any more — loan balances have been updated.</p>
                </div>
            </div>
            <div class="pr-next-actions" style="margin-top:1rem">
                <a class="pp-btn pp-btn-light" href="{{ route('payroll.runs.payslips', $run) }}"><i class="bi bi-receipt"></i> Payslips</a>
                @if($canPrepare)
                    <form method="POST" action="{{ route('payroll.email.bulk', $run) }}" onsubmit="event.stopPropagation(); return confirm('Email every payslip in this payroll now?')">@csrf
                        <button class="pp-btn pp-btn-ghost"><i class="bi bi-envelope"></i> Email all payslips</button>
                    </form>
                @endif
                @if(auth()->user()->hasPermission('reports.view'))
                    <a class="pp-btn pp-btn-ghost" href="{{ route('payroll.reports.bank_payments.preview', $run) }}"><i class="bi bi-bank"></i> Bank file</a>
                    <a class="pp-btn pp-btn-ghost" href="{{ route('payroll.reports.index', ['run_id' => $run->id]) }}"><i class="bi bi-file-earmark-bar-graph"></i> ZRA · NAPSA · NHIMA returns</a>
                @endif
                @if($run->status === \App\Models\PayrollRun::APPROVED && $canPrepare)
                    <form method="POST" action="{{ route('payroll.runs.paid', $run) }}" onsubmit="event.stopPropagation(); return confirm('Mark this payroll as paid?')">@csrf
                        <button class="pp-btn pp-btn-ghost"><i class="bi bi-check2-all"></i> Mark as paid</button>
                    </form>
                @endif
            </div>
        </section>
    @endif

    {{-- ================= KPIs ================= --}}
    <div class="pr-kpis">
        @foreach($kpis as $i => [$label, $value, $before, $money])
            @php $d = $delta($value, $before); @endphp
            <div class="pr-kpi {{ $i === 5 ? 'main' : '' }}">
                <span>{{ $label }}</span>
                <b>{{ $money ? $k($value) : $value }}</b>
                @if($d)<span class="pr-delta {{ $d[0] }}">{{ $money || $d[0] === 'same' ? $d[1] : preg_replace('/K ([\d,]+)\.00/', '$1', $d[1]) }}</span>@elseif(! $previous)<span class="pr-delta">first payroll</span>@endif
            </div>
        @endforeach
    </div>

    <div class="pr-layout">
        <div>
            {{-- ================= HIGHLIGHTS ================= --}}
            <section class="pr-card" id="prHighlights">
                <div class="pr-card-head">
                    <div><h2>Highlights</h2><div class="sub">What changed, who's away, who owes, who worked extra.</div></div>
                    @if($canEdit)
                        <form method="POST" action="{{ route('payroll.runs.overtime', $run) }}" id="prOtForm">@csrf
                            <input type="hidden" name="include_overtime" value="0">
                            <label class="pr-switch"><input type="checkbox" name="include_overtime" value="1" @checked($run->include_overtime) onchange="this.form.submit()"> Include approved overtime</label>
                        </form>
                    @endif
                </div>
                <div class="pr-sev">
                    <button type="button" class="all on" data-sev=""><b>{{ $insights['items']->count() }}</b> All</button>
                    <button type="button" class="fix" data-sev="fix"><span class="dot"></span><b>{{ $insights['counts']['fix'] }}</b> Needs fixing</button>
                    <button type="button" class="check" data-sev="check"><span class="dot"></span><b>{{ $insights['counts']['check'] }}</b> Worth checking</button>
                    <button type="button" class="info" data-sev="info"><span class="dot"></span><b>{{ $insights['counts']['info'] }}</b> Good to know</button>
                </div>
                @if($insights['items']->isEmpty())
                    <div class="pr-allclear"><i class="bi bi-patch-check"></i><b>All clear.</b> Nobody on leave, no loans or overtime, nothing unusual.</div>
                @else
                    <div class="pr-hl">
                        @foreach($insights['items'] as $i => $h)
                            <div class="pr-hl-item {{ $h['severity'] }}" data-sev="{{ $h['severity'] }}" style="animation-delay:{{ min($i, 12) * 30 }}ms">
                                <span class="ic"><i class="bi {{ $h['icon'] }}"></i></span>
                                <div style="min-width:0">
                                    <div class="t">{{ $h['title'] }}</div>
                                    <b>{{ $h['name'] ?? '—' }}</b>
                                    <p>{{ $h['detail'] }}</p>
                                </div>
                                @if(! empty($h['link']) && ($canEdit || ! in_array($h['kind'], ['no_pay', 'no_bank', 'contract_over', 'no_tpin'])))
                                    <a class="go" href="{{ $h['link'] }}">{{ $h['severity'] === 'fix' ? 'Fix' : 'Open' }} <i class="bi bi-arrow-right"></i></a>
                                @elseif($h['employee_id'] && ($pid = $rows->firstWhere('employee.id', $h['employee_id'])['payroll']->id ?? null))
                                    <a class="go" href="#" data-open="{{ $pid }}">View pay <i class="bi bi-arrow-right"></i></a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- ================= PEOPLE ================= --}}
            <section class="pr-card">
                <div class="pr-card-head">
                    <div><h2>People</h2><div class="sub">Click anyone to see their payslip lines{{ $canEdit ? ' or add a one-off payment or deduction' : '' }}.</div></div>
                </div>
                <div class="pr-chips">
                    <button type="button" class="pr-chip on" data-kind=""><i class="bi bi-people"></i> Everyone <b>{{ $rows->count() }}</b></button>
                    @foreach([['fix', 'bi-exclamation-octagon', 'Needs fixing', $rows->filter(fn ($r) => collect($r['flags'])->contains('severity', 'fix'))->count()],
                              ['leave', 'bi-sun', 'On leave', $insights['chips']['leave']],
                              ['loan', 'bi-piggy-bank', 'Loans', $insights['chips']['loan']],
                              ['overtime', 'bi-alarm', 'Overtime', $insights['chips']['overtime']],
                              ['changed', 'bi-graph-up-arrow', 'Big change', $insights['chips']['changed']],
                              ['new', 'bi-stars', 'New', $insights['chips']['joiners']]] as [$kind, $ic, $label, $n])
                        @if($n)<button type="button" class="pr-chip" data-kind="{{ $kind }}"><i class="bi {{ $ic }}"></i> {{ $label }} <b>{{ $n }}</b></button>@endif
                    @endforeach
                </div>
                <div class="pr-tools">
                    <div class="pr-search"><i class="bi bi-search"></i><input type="search" id="prSearch" placeholder="Search name, department or job…"></div>
                </div>
                @if($rows->isEmpty())
                    <div class="pr-empty"><i class="bi bi-calculator"></i>Nobody calculated yet. {{ $canPrepare ? 'Press Recalculate above.' : '' }}</div>
                @else
                <div class="pr-tablewrap">
                    <table class="pr-table" id="prTable">
                        <thead><tr><th>Employee</th><th>Gross</th><th>PAYE</th><th>NAPSA</th><th>NHIMA</th><th>Other</th><th>Take-home</th><th>vs last</th></tr></thead>
                        <tbody>
                        @foreach($rows as $r)
                            @php
                                $e = $r['employee'];
                                $kinds = collect($r['flags'])->keys()->merge(collect($r['flags'])->pluck('severity'))
                                    ->map(fn ($x) => $x === 'unpaid_leave' ? 'leave' : ($x === 'joiner' ? 'new' : $x))->unique()->implode(' ');
                                $vs = $r['prev'] !== null ? $r['net'] - $r['prev'] : null;
                            @endphp
                            <tr data-open="{{ $r['payroll']->id }}" data-kinds="{{ $kinds }}" data-search="{{ strtolower(($e?->full_name ?? '') . ' ' . ($e?->department ?? '') . ' ' . ($e?->position ?? '') . ' ' . ($e?->employee_id ?? '')) }}">
                                <td>
                                    <div class="pr-person">
                                        <span class="pr-av">@if($e?->photo_url)<img src="{{ $e->photo_url }}" alt="">@else{{ $e?->initials }}@endif</span>
                                        <div>
                                            <b>{{ $e?->full_name ?? 'Unknown' }}
                                                @if($r['flags'])
                                                    <span class="pr-flags">
                                                        @foreach(collect($r['flags'])->take(4) as $f)
                                                            <span class="pr-flag {{ $f['severity'] }}" title="{{ $f['title'] }}: {{ $f['detail'] }}"><i class="bi {{ $f['icon'] }}"></i></span>
                                                        @endforeach
                                                    </span>
                                                @endif
                                            </b>
                                            <small>{{ $e?->position }}{{ $e?->department ? ' · ' . $e->department : '' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ number_format($r['gross'], 2) }}</td>
                                <td>{{ number_format($r['paye'], 2) }}</td>
                                <td>{{ number_format($r['napsa'], 2) }}</td>
                                <td>{{ number_format($r['nhima'], 2) }}</td>
                                <td>{{ $r['other'] > 0.004 ? number_format($r['other'], 2) : '—' }}</td>
                                <td class="pr-net">{{ number_format($r['net'], 2) }}</td>
                                <td>
                                    @if($vs === null)<span class="pr-vs same">new</span>
                                    @elseif(abs($vs) < 0.005)<span class="pr-vs same">—</span>
                                    @else<span class="pr-vs {{ $vs > 0 ? 'up' : 'down' }}">{{ $vs > 0 ? '+' : '−' }}{{ number_format(abs($vs), 0) }}</span>@endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot><tr>
                            <td>Total · {{ $rows->count() }} people</td>
                            <td>{{ number_format($rows->sum('gross'), 2) }}</td>
                            <td>{{ number_format($rows->sum('paye'), 2) }}</td>
                            <td>{{ number_format($rows->sum('napsa'), 2) }}</td>
                            <td>{{ number_format($rows->sum('nhima'), 2) }}</td>
                            <td>{{ number_format($rows->sum('other'), 2) }}</td>
                            <td>{{ number_format($rows->sum('net'), 2) }}</td>
                            <td></td>
                        </tr></tfoot>
                    </table>
                </div>
                @endif
            </section>
        </div>

        {{-- ================= APPROVAL SIDEBAR ================= --}}
        <aside class="pr-side">
            <section class="pr-card">
                <div class="pr-card-head" style="margin-bottom:.8rem">
                    <h2 style="font-size:1.05rem">Approval chain</h2>
                    @if(auth()->user()->hasPermission('payroll.finalize'))<a class="pp-link" style="font-size:.78rem" href="{{ route('payroll-approvals.index') }}">Change</a>@endif
                </div>
                <ol class="pr-chain">
                    @php $sub = $run->events->where('action', 'submitted')->last(); @endphp
                    <li class="{{ $run->isEditable() ? 'current' : 'done' }}">
                        <span class="node"><i class="bi {{ $run->isEditable() ? 'bi-pencil' : 'bi-check-lg' }}"></i></span>
                        <div><b>Prepared</b>
                            <small>{{ $run->isEditable() ? 'In progress' : 'by ' . ($run->submitter?->name ?? '—') . ' · ' . optional($run->submitted_at)->format('j M, H:i') }}</small>
                            @if(! $run->isEditable() && $sub?->comment)<div class="quote">“{{ $sub->comment }}”</div>@endif
                        </div>
                    </li>
                    @foreach($steps as $s)
                        <li class="{{ $s['state'] }}">
                            <span class="node">@if($s['state'] === 'done')<i class="bi bi-check-lg"></i>@elseif($s['state'] === 'current')<i class="bi bi-hourglass-split"></i>@else{{ $s['position'] }}@endif</span>
                            <div><b>{{ $s['name'] }}</b>
                                <small>
                                    @if($s['event']) {{ $s['event']->user?->name }} · {{ $s['event']->created_at->format('j M, H:i') }}
                                    @else {{ $s['who'] }}@if($s['people']->isNotEmpty() && empty($s['user_id'])) · {{ $s['people']->take(3)->implode(', ') }}@endif
                                    @endif
                                </small>
                                @if($s['event']?->comment)<div class="quote">“{{ $s['event']->comment }}”</div>@endif
                                @if($s['people']->isEmpty())<small style="color:var(--zp-red)">Nobody can approve this step yet — check the approval chain.</small>@endif
                            </div>
                        </li>
                    @endforeach
                    <li class="{{ $run->isLocked() ? 'locked done' : '' }}">
                        <span class="node"><i class="bi bi-lock{{ $run->isLocked() ? '-fill' : '' }}"></i></span>
                        <div><b>Locked</b><small>{{ $run->isLocked() ? optional($run->finalized_at)->format('j M Y, H:i') : 'After the last approval' }}</small></div>
                    </li>
                </ol>
            </section>

            @if($run->events->isNotEmpty())
                <section class="pr-card">
                    <div class="pr-card-head" style="margin-bottom:.7rem"><h2 style="font-size:1.05rem">History</h2></div>
                    <ul class="pr-history">
                        @foreach($run->events->sortByDesc('id')->take(10) as $ev)
                            <li class="{{ $ev->action }}">
                                <i class="bi {{ ['calculated' => 'bi-calculator', 'submitted' => 'bi-send', 'approved' => 'bi-check-circle', 'sent_back' => 'bi-arrow-return-left', 'recalled' => 'bi-arrow-counterclockwise', 'locked' => 'bi-lock-fill', 'paid' => 'bi-check2-all'][$ev->action] ?? 'bi-dot' }}"></i>
                                <div>
                                    <b>{{ \App\Models\PayrollRunEvent::LABELS[$ev->action] ?? $ev->action }}</b>{{ $ev->step_name ? ' · ' . $ev->step_name : '' }}
                                    <small>{{ $ev->user?->name ?? 'System' }} · {{ $ev->created_at->diffForHumans() }}@if(isset($ev->snapshot['net_pay'])) · {{ $k($ev->snapshot['net_pay']) }}@endif</small>
                                    @if($ev->comment)<q>{{ $ev->comment }}</q>@endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if($canEdit || $run->isEditable())
                <div class="pp-callout" style="margin:0"><i class="bi bi-shield-lock"></i><div>Once submitted, figures are frozen. If an approver sends it back you'll see their comment here.</div></div>
            @endif
        </aside>
    </div>
</div>

@push('modals')
{{-- ================= DRAWER (outside the animated page wrapper so position:fixed works) ================= --}}
<div class="pr pp">
<div class="pr-drawer-bg" id="prDrawerBg"></div>
<aside class="pr-drawer" id="prDrawer" aria-hidden="true">
    <div class="pr-drawer-head">
        <span class="pr-av" id="dAv"></span>
        <div style="min-width:0"><h3 id="dName"></h3><small id="dRole"></small></div>
        <button type="button" class="pr-drawer-x" id="dClose" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="pr-drawer-body">
        <div class="pr-net-big"><div><span>Take-home</span><br><b id="dNet"></b></div><div style="text-align:right"><span>Last month</span><br><span id="dPrev" style="font-size:.95rem;color:#fff"></span></div></div>
        <div id="dFlags"></div>
        <ul class="pr-lines" id="dEarn"></ul>
        <ul class="pr-lines" id="dDed"></ul>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a class="pp-btn pp-btn-ghost pp-btn-sm" id="dSlip" target="_blank"><i class="bi bi-receipt"></i> Full payslip</a>
            <a class="pp-btn pp-btn-ghost pp-btn-sm" id="dProfile"><i class="bi bi-person"></i> Profile</a>
        </div>
        @if($canEdit)
            <form class="pr-adj" id="dAdj">
                <b style="font-size:.88rem">Add a one-off for this month</b>
                <div class="pp-muted" style="font-size:.76rem">E.g. bonus, backpay, advance recovery. Tax is recalculated automatically.</div>
                <div class="row2">
                    <select name="type"><option value="earning">Payment (+)</option><option value="deduction">Deduction (−)</option></select>
                    <input name="value" inputmode="decimal" placeholder="Amount (K)" required>
                </div>
                <div class="row2">
                    <input name="name" placeholder="What is it? e.g. Bonus" required>
                    <select name="tax_profile"><option value="taxable">Taxed (PAYE + NAPSA)</option><option value="napsa_only">NAPSA only</option><option value="non_taxable">Not taxed</option></select>
                </div>
                <button class="pp-btn pp-btn-primary pp-btn-sm" style="margin-top:.6rem;width:100%"><i class="bi bi-plus-lg"></i> <span>Add &amp; recalculate</span></button>
                <div class="pp-error" id="dErr" hidden style="margin:.6rem 0 0"></div>
            </form>
        @endif
    </div>
</aside>
</div>
@endpush
@endsection

@push('scripts')
<script src="{{ asset('assets/js/zp-people.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const DATA = @json($drawerRows);
    const root = document.getElementById('prRun');
    const $ = (s) => document.querySelector(s);
    const esc = zpPeople.esc, money = (n) => zpPeople.money(n);
    let current = null;

    // highlight severity filter
    root.querySelectorAll('.pr-sev button').forEach(b => b.addEventListener('click', () => {
        root.querySelectorAll('.pr-sev button').forEach(x => x.classList.toggle('on', x === b));
        root.querySelectorAll('.pr-hl-item').forEach(i => i.hidden = !!b.dataset.sev && i.dataset.sev !== b.dataset.sev);
    }));

    // people filter + search
    let kind = '';
    const filter = () => {
        const q = ($('#prSearch')?.value || '').toLowerCase().trim();
        document.querySelectorAll('#prTable tbody tr').forEach(tr => {
            const okK = !kind || (' ' + tr.dataset.kinds + ' ').includes(' ' + kind + ' ');
            tr.hidden = !(okK && (!q || tr.dataset.search.includes(q)));
        });
    };
    root.querySelectorAll('.pr-chip').forEach(c => c.addEventListener('click', () => {
        kind = c.dataset.kind; root.querySelectorAll('.pr-chip').forEach(x => x.classList.toggle('on', x === c)); filter();
    }));
    $('#prSearch')?.addEventListener('input', filter);

    // drawer
    const open = (pid) => {
        const d = DATA[pid]; if (!d) return; current = d;
        $('#dAv').innerHTML = d.photo ? '<img src="' + esc(d.photo) + '" alt="">' : esc(d.ini);
        $('#dName').textContent = d.name; $('#dRole').textContent = d.role;
        $('#dNet').textContent = money(d.net);
        $('#dPrev').textContent = d.prev === null ? 'first payroll' : money(d.prev);
        $('#dFlags').innerHTML = d.flags.map(f => '<div class="pr-hl-item ' + f.s + '" style="margin-bottom:.45rem"><span class="ic"><i class="bi ' + f.i + '"></i></span><div><div class="t">' + esc(f.t) + '</div><p>' + esc(f.d) + '</p></div></div>').join('');
        const list = (title, items, total) => '<h4>' + title + '</h4>' + items.map(i => '<li><span>' + esc(i.d) + '</span><b>' + money(i.a) + '</b></li>').join('') + '<li class="total"><span>Total</span><b>' + money(total) + '</b></li>';
        $('#dEarn').innerHTML = list('Earnings', d.earn, d.earn.reduce((s, i) => s + i.a, 0));
        $('#dDed').innerHTML = list('Deductions', d.ded, d.ded.reduce((s, i) => s + i.a, 0));
        $('#dSlip').href = d.slip;
        $('#dProfile').href = d.profile || '#';
        $('#prDrawer').classList.add('open'); $('#prDrawerBg').classList.add('open');
        $('#prDrawer').setAttribute('aria-hidden', 'false');
    };
    const close = () => { $('#prDrawer').classList.remove('open'); $('#prDrawerBg').classList.remove('open'); $('#prDrawer').setAttribute('aria-hidden', 'true'); };
    document.addEventListener('click', (e) => {
        const t = e.target.closest('[data-open]');
        if (t) { e.preventDefault(); open(t.dataset.open); }
    });
    $('#dClose').addEventListener('click', close);
    $('#prDrawerBg').addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });

    // one-off adjustment
    const adj = document.getElementById('dAdj');
    if (adj) {
        adj.type.addEventListener('change', () => adj.tax_profile.style.visibility = adj.type.value === 'earning' ? 'visible' : 'hidden');
        adj.addEventListener('submit', async (e) => {
            e.preventDefault(); e.stopPropagation();
            const btn = adj.querySelector('button'); btn.disabled = true; btn.querySelector('span').textContent = 'Recalculating…';
            const fd = new FormData(adj);
            const body = { employee_id: current.eid, name: fd.get('name'), type: fd.get('type'), formula_type: 'fixed', value: String(fd.get('value')).replace(/[^\d.]/g, ''), tax_profile: fd.get('type') === 'earning' ? fd.get('tax_profile') : null };
            const res = await fetch(root.dataset.adjust, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(body) });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success !== false) { location.reload(); return; }
            const err = document.getElementById('dErr'); err.hidden = false;
            err.textContent = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not save that.');
            btn.disabled = false; btn.querySelector('span').textContent = 'Add & recalculate';
        });
    }

    // busy buttons + "send back needs a comment"
    document.querySelectorAll('[data-busy]').forEach(b => b.closest('form')?.addEventListener('submit', (e) => {
        if (e.submitter && e.submitter !== b) return;
        b.disabled = true; const s = b.querySelector('span'); if (s) s.textContent = b.dataset.busy;
    }));
    document.querySelector('[data-needs-comment]')?.addEventListener('click', (e) => {
        const t = document.querySelector('#prDecide textarea');
        if (!t.value.trim()) { e.preventDefault(); t.focus(); t.placeholder = 'Please say what needs changing before sending it back'; t.style.borderColor = '#fff'; }
    });

    @if(session('celebrate')) zpPeople.confetti(); @endif
});
</script>
@endpush
