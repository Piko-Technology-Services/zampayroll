@extends('layouts.portal')
@section('title', 'Leave')

@php
    $fmtDays = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
    $items = $requests->map(fn ($r) => [
        'title' => $typeInfo[$r->leave_type]->name ?? $r->typeName(),
        'detail' => $r->start_date->format('D d M') . ' – ' . $r->end_date->format('D d M Y') . ', ' . $fmtDays($r->days) . ' working day(s), back ' . optional($r->return_date)->format('d M'),
        'status' => $r->status,
        'label' => $r->status === 'manager_approved' ? 'Manager OK' : $r->statusLabel(),
        'note' => $r->hr_comment ?: $r->cancel_reason,
        'cancelUrl' => route('portal.leave.cancel', $r),
        'canCancel' => $r->isOpen() || ($r->status === 'approved' && $r->start_date->isAfter(today())),
        'cancelConfirm' => $r->status === 'approved' ? 'Cancel this approved leave? The days go back to your balance.' : 'Cancel this request?',
    ]);
@endphp

@section('content')
<div class="pt-page-head">
    <div>
        <h1>Leave</h1>
        <p>Pick your dates — weekends and public holidays are left out automatically.</p>
    </div>
    <div style="display:flex;gap:.6rem">
        <div class="pt-stat">Days left<b>{{ $balance['balance'] !== null ? $fmtDays($balance['balance']) : '—' }}</b></div>
        @if($balance['pending'] > 0)<div class="pt-stat">Waiting<b>{{ $fmtDays($balance['pending']) }}</b></div>@endif
        <div class="pt-stat">Taken in {{ now()->year }}<b>{{ $fmtDays($usedThisYear) }}</b></div>
    </div>
</div>

<div class="pt-grid">
    <div class="pt-card pt-span-5" id="apply">
        <h3>New leave request</h3>
        <form method="POST" action="{{ route('portal.leave.store') }}" enctype="multipart/form-data" novalidate>
            @csrf
            <div class="zp-field">
                <label class="zp-label" for="leave_type">Type of leave</label>
                <select class="zp-select @error('leave_type') is-invalid @enderror" name="leave_type" id="leave_type" required>
                    @foreach($types as $k => $label)<option value="{{ $k }}" data-desc="{{ $typeInfo[$k]->description }}" data-paid="{{ $typeInfo[$k]->is_paid ? 1 : 0 }}" @selected(old('leave_type', 'annual') === $k)>{{ $label }}{{ $typeInfo[$k]->is_paid ? '' : ' (unpaid)' }}</option>@endforeach
                </select>
                <div class="zp-hint" id="typeDesc"></div>
                @error('leave_type')<div class="zp-error">{{ $message }}</div>@enderror
            </div>
            <div class="ob-grid">
                <div class="zp-field">
                    <label class="zp-label" for="start_date">First day off</label>
                    <input class="zp-input @error('start_date') is-invalid @enderror" type="date" name="start_date" id="start_date" value="{{ old('start_date') }}" required>
                    @error('start_date')<div class="zp-error">{{ $message }}</div>@enderror
                </div>
                <div class="zp-field">
                    <label class="zp-label" for="end_date">Last day off</label>
                    <input class="zp-input @error('end_date') is-invalid @enderror" type="date" name="end_date" id="end_date" value="{{ old('end_date') }}" required>
                    @error('end_date')<div class="zp-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="pt-estimate" id="leaveEstimate" hidden>
                <span>Working days<br><b id="estDays">0</b></span>
                <span style="text-align:right">Back at work<br><strong id="estReturn">—</strong></span>
            </div>
            <div class="zp-hint" id="estHolidays" style="margin:-.5rem 0 1rem"></div>
            <div class="zp-error" id="estProblem" style="margin:-.5rem 0 1rem" hidden></div>

            <div class="zp-field">
                <label class="zp-label" for="reason">Note for HR <small>(optional)</small></label>
                <textarea class="zp-textarea" name="reason" id="reason" rows="3" placeholder="e.g. Family trip to Livingstone">{{ old('reason') }}</textarea>
            </div>
            <div class="zp-field">
                <label class="zp-label" for="supervisor_email">Copy my supervisor <small>(optional)</small></label>
                <input class="zp-input" type="email" name="supervisor_email" id="supervisor_email" value="{{ old('supervisor_email') }}" placeholder="supervisor@company.co.zm">
            </div>
            <div class="zp-field">
                <label class="zp-label" for="documents">Attachments <small id="docNeed">(e.g. sick note — PDF or image, 5 MB max)</small></label>
                <input class="zp-input @error('documents') is-invalid @enderror" type="file" name="documents[]" id="documents" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                @error('documents')<div class="zp-error">{{ $message }}</div>@enderror
            </div>
            <button class="zp-btn zp-btn-primary zp-btn-block" type="submit"><i class="bi bi-send"></i> Send leave request</button>
        </form>
    </div>

    <div class="pt-card pt-span-7">
        <h3>My leave requests</h3>
        @include('portal._request-list', ['items' => $items, 'icon' => 'bi-calendar2-heart', 'empty' => 'No leave requests yet.'])
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const s = document.getElementById('start_date'), e = document.getElementById('end_date');
    const box = document.getElementById('leaveEstimate');
    const t = document.getElementById('leave_type'), prob = document.getElementById('estProblem');
    function desc() { const o = t.selectedOptions[0]; document.getElementById('typeDesc').textContent = (o && o.dataset.desc) || ''; }
    async function calc() {
        desc();
        if (!s.value || !e.value || e.value < s.value) { box.hidden = true; prob.hidden = true; return; }
        const r = await fetch(@json(route('portal.leave.calculate')) + `?leave_type=${encodeURIComponent(t.value)}&start_date=${s.value}&end_date=${e.value}`, { headers: { Accept: 'application/json' } });
        if (!r.ok) return;
        const d = await r.json();
        box.hidden = false;
        document.getElementById('estDays').textContent = d.days;
        document.getElementById('estReturn').textContent = d.return_date;
        document.getElementById('estHolidays').textContent = d.holidays.length ? 'Public holidays skipped: ' + d.holidays.join(', ') : '';
        prob.hidden = d.ok; prob.textContent = d.ok ? '' : d.message;
        document.getElementById('docNeed').textContent = d.needs_document ? '(required for this leave)' : '(e.g. sick note — PDF or image, 5 MB max)';
    }
    t.addEventListener('change', calc);
    s.addEventListener('change', () => { if (!e.value || e.value < s.value) e.value = s.value; e.min = s.value; calc(); });
    e.addEventListener('change', calc);
    calc();
})();
</script>
@endpush
