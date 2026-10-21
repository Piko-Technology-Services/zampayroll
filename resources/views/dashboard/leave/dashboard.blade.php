@extends('layouts.app')

@section('title', 'Leave requests · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-requests.css') }}">
@endpush

@php
    $u = auth()->user();
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
    $tabs = ['open' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'all' => 'All'];
    $canApprove = $u->hasPermission('leave.approve');
@endphp

@section('content')
<div class="pr pp rq">
    <div class="pr-head">
        <div>
            <h1>Leave requests</h1>
            <p>
                @if($hrConfirm) Managers approve first, then HR confirms. @else A manager or HR approves; HR can see and change everything. @endif
                Approving updates the balance, the leave sheet and attendance.
            </p>
        </div>
        <div class="pr-head-actions">
            @if($canApprove)
                <button type="button" class="pp-btn pp-btn-primary" data-rq-open="rqRecord"><i class="bi bi-plus-lg"></i> Record leave</button>
            @endif
        </div>
    </div>

    <div class="rq-kpis">
        <div class="rq-kpi main"><span>Waiting for a decision</span><b>{{ $counts['open'] }}</b></div>
        <div class="rq-kpi"><span>On leave today</span><b>{{ $onLeaveToday }}</b></div>
        <div class="rq-kpi"><span>Starting in the next 2 weeks</span><b>{{ $upcoming }}</b></div>
        <div class="rq-kpi"><span>Approved (all time)</span><b>{{ $counts['approved'] }}</b></div>
    </div>

    <div class="rq-bar">
        <nav class="rq-tabs">
            @foreach($tabs as $key => $label)
                <a href="{{ route('leave.dashboard', array_filter(['status' => $key, 'q' => request('q'), 'type' => request('type')])) }}" class="rq-tab {{ $status === $key ? 'on' : '' }}">
                    {{ $label }} @isset($counts[$key])<span class="n">{{ $counts[$key] }}</span>@endisset
                </a>
            @endforeach
        </nav>
        <form class="rq-filters" method="GET">
            <input type="hidden" name="status" value="{{ $status }}">
            <input class="pp-input" type="search" name="q" value="{{ request('q') }}" placeholder="Search a name or number">
            <select class="pp-select" name="type" onchange="this.form.submit()">
                <option value="">All types</option>
                @foreach($types as $t)<option value="{{ $t->key }}" @selected(request('type') === $t->key)>{{ $t->name }}</option>@endforeach
            </select>
        </form>
    </div>

    <div class="rq-list">
        @forelse($requests as $req)
            @php
                $emp = $req->employee;
                $type = $types->firstWhere('key', $req->leave_type);
                $decide = $service->canDecide($u, $req);
                $cancel = $service->canCancel($u, $req);
            @endphp
            <article class="rq-item {{ $decide ? 'attn' : '' }}">
                <div class="rq-av">{{ $emp?->initials }}</div>
                <div class="rq-main">
                    <div class="rq-who">
                        <b>{{ $emp?->full_name ?? 'Former employee' }}</b>
                        <small>{{ $emp?->employee_id }}{{ $emp?->department ? ' · ' . $emp->department : '' }}</small>
                        <span class="rq-type {{ $type && ! $type->is_paid ? 'unpaid' : '' }}">{{ $typeNames[$req->leave_type] ?? $req->typeName() }}</span>
                    </div>
                    <div class="rq-what">
                        <span><i class="bi bi-calendar-range"></i> {{ $req->start_date->format('D j M') }} – {{ $req->end_date->format('D j M Y') }}</span>
                        <span><i class="bi bi-hourglass-split"></i> <b>{{ $fmt($req->days) }}</b> working day{{ (float) $req->days === 1.0 ? '' : 's' }}</span>
                        @if($req->return_date)<span><i class="bi bi-box-arrow-in-right"></i> back {{ $req->return_date->format('D j M') }}</span>@endif
                        @if($type?->deducts_balance && $emp && $emp->leave_days_balance !== null && $req->isOpen())
                            <span><i class="bi bi-piggy-bank"></i> {{ $fmt($emp->leave_days_balance) }} days left now</span>
                        @endif
                    </div>
                    @if($req->reason)<div class="rq-note">“{{ $req->reason }}”</div>@endif
                    @if($req->documents)
                        <div class="rq-docs">
                            @foreach($req->documents as $doc)
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($doc['path']) }}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> {{ \Illuminate\Support\Str::limit($doc['name'] ?? 'Document', 28) }}</a>
                            @endforeach
                        </div>
                    @endif
                    <div class="rq-trail">
                        <span><i class="bi bi-send"></i>Asked {{ $req->created_at->diffForHumans() }}{{ $req->recorded_by ? ' (entered by HR)' : '' }}</span>
                        @if($req->manager_approved_at && $req->status !== 'approved')<span><i class="bi bi-person-check"></i>Manager OK: {{ $req->managerApprovedBy?->name }}</span>@endif
                        @if($req->reviewed_at && in_array($req->status, ['approved', 'rejected'], true))<span><i class="bi bi-check2-circle"></i>{{ ucfirst($req->status) }} by {{ $req->reviewedBy?->name }} · {{ $req->reviewed_at->format('j M') }}</span>@endif
                        @if($req->hr_comment)<span><i class="bi bi-chat-left-text"></i>{{ $req->hr_comment }}</span>@endif
                        @if($req->status === 'cancelled')<span><i class="bi bi-x-circle"></i>Cancelled {{ $req->cancelled_by_employee ? 'by the employee' : 'by ' . ($req->cancelledBy?->name ?? 'HR') }} · {{ optional($req->cancelled_at)->format('j M') }}{{ (float) $req->deducted_days > 0 ? ' · ' . $fmt($req->deducted_days) . ' days returned' : '' }}{{ $req->cancel_reason ? ' — ' . $req->cancel_reason : '' }}</span>@endif
                    </div>
                </div>
                <div class="rq-side">
                    <span class="rq-pill {{ $req->status }}">{{ $req->statusLabel() }}</span>
                    @if($decide)
                        <form method="POST" action="{{ route('leave.approve', $req) }}" class="rq-decide">
                            @csrf
                            <input class="pp-input" type="text" name="comment" maxlength="500" placeholder="Comment (optional)">
                            <button class="pp-btn pp-btn-sm rq-btn-no" formaction="{{ route('leave.reject', $req) }}"><i class="bi bi-x-lg"></i> Reject</button>
                            <button class="pp-btn pp-btn-sm pp-btn-primary"><i class="bi bi-check-lg"></i> {{ $req->status === 'manager_approved' ? 'Confirm' : 'Approve' }}</button>
                        </form>
                    @endif
                    @if($cancel)
                        <details class="rq-more">
                            <summary><i class="bi bi-x-circle"></i> Cancel {{ $req->status === 'approved' ? 'this leave' : 'request' }}</summary>
                            <form method="POST" action="{{ route('leave.cancel', $req) }}">
                                @csrf
                                <input class="pp-input" type="text" name="reason" maxlength="500" placeholder="Reason" style="width:170px;padding:.45rem .6rem;font-size:.8rem">
                                <button class="pp-btn pp-btn-sm rq-btn-no">Cancel it</button>
                            </form>
                        </details>
                    @endif
                </div>
            </article>
        @empty
            <div class="rq-empty"><i class="bi bi-cup-hot"></i>{{ $status === 'open' ? 'Nothing waiting. All caught up!' : 'No leave requests here.' }}</div>
        @endforelse
    </div>

    <div style="margin-top:1rem">{{ $requests->links() }}</div>
</div>
@endsection

@push('modals')
@if($canApprove)
<div class="rq-modal-bg" id="rqRecord" data-check="{{ route('leave.check') }}">
    <form class="rq-modal" method="POST" action="{{ route('leave.record') }}" enctype="multipart/form-data">
        @csrf
        <h2 class="zp-display">Record leave</h2>
        <div class="sub">For someone who phoned in sick, or leave agreed in person. The same rules apply.</div>
        <div class="pp-field">
            <label for="rqEmp">Employee</label>
            <select class="pp-select" name="employee_id" id="rqEmp" required>
                <option value="">Choose…</option>
                @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->first_name }} {{ $e->last_name }} · {{ $e->employee_id }}</option>@endforeach
            </select>
        </div>
        <div class="pp-field">
            <label for="rqType">Type of leave</label>
            <select class="pp-select" name="leave_type" id="rqType" required>
                @foreach($types->where('is_active', true) as $t)<option value="{{ $t->key }}">{{ $t->name }}{{ $t->is_paid ? '' : ' (unpaid)' }}</option>@endforeach
            </select>
        </div>
        <div class="rq-grid2">
            <div class="pp-field"><label for="rqStart">First day off</label><input class="pp-input" type="date" name="start_date" id="rqStart" required></div>
            <div class="pp-field"><label for="rqEnd">Last day off</label><input class="pp-input" type="date" name="end_date" id="rqEnd" required></div>
        </div>
        <div class="rq-calc" id="rqCalc" hidden></div>
        <div class="pp-field"><label for="rqReason">Note <small>(optional)</small></label><input class="pp-input" type="text" name="reason" id="rqReason" maxlength="1000"></div>
        <div class="pp-field"><label for="rqDocs">Documents <small>(optional)</small></label><input class="pp-input" type="file" name="documents[]" id="rqDocs" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"></div>
        <label class="rq-check"><input type="checkbox" name="approve_now" value="1" checked> Approve it now</label>
        <div class="rq-modal-actions">
            <button type="button" class="pp-btn pp-btn-ghost" data-rq-close>Close</button>
            <button class="pp-btn pp-btn-primary"><i class="bi bi-check-lg"></i> Save leave</button>
        </div>
    </form>
</div>
@endif
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-rq-open]').forEach(b => b.addEventListener('click', () => document.getElementById(b.dataset.rqOpen).classList.add('open')));
    document.querySelectorAll('.rq-modal-bg').forEach(bg => {
        bg.addEventListener('click', e => { if (e.target === bg || e.target.closest('[data-rq-close]')) bg.classList.remove('open'); });
    });
    const m = document.getElementById('rqRecord'); if (!m) return;
    const f = id => document.getElementById(id), out = f('rqCalc');
    const fmt = n => (Math.round(n * 10) / 10).toString();
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    async function calc() {
        if (!f('rqEmp').value || !f('rqStart').value || !f('rqEnd').value) { out.hidden = true; return; }
        if (f('rqEnd').value < f('rqStart').value) f('rqEnd').value = f('rqStart').value;
        const q = new URLSearchParams({ employee_id: f('rqEmp').value, leave_type: f('rqType').value, start_date: f('rqStart').value, end_date: f('rqEnd').value });
        const r = await fetch(m.dataset.check + '?' + q, { headers: { Accept: 'application/json' } });
        if (!r.ok) return;
        const d = await r.json(), b = d.balance || {};
        out.hidden = false;
        out.classList.toggle('bad', !d.ok);
        out.innerHTML = d.ok
            ? '<span>Working days<b>' + fmt(d.days) + '</b></span><span>Back at work<b style="font-size:1rem">' + esc(d.return_date) + '</b></span>' + (b.available !== null && b.available !== undefined ? '<span>Leave days left<b>' + fmt(b.available) + '</b></span>' : '')
            : '<span><i class="bi bi-exclamation-triangle"></i> ' + esc(d.message) + '</span>';
    }
    ['rqEmp', 'rqType', 'rqStart', 'rqEnd'].forEach(id => f(id).addEventListener('change', calc));
});
</script>
@endpush
