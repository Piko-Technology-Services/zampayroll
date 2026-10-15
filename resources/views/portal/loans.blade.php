@extends('layouts.portal')
@section('title', 'Loans')

@php
    $items = $requests->map(fn ($r) => [
        'title' => 'K' . number_format((float) $r->amount, 2) . ' requested',
        'detail' => ($plans[$r->payment_plan] ?? $r->payment_plan) . ($r->installment_amount ? ', K' . number_format((float) $r->installment_amount, 2) . ' per deduction' : '') . ' — ' . $r->created_at->format('d M Y'),
        'status' => $r->status,
        'note' => $r->hr_comment,
        'cancelUrl' => route('portal.loans.cancel', $r),
    ]);
@endphp

@section('content')
<div class="pt-page-head">
    <div>
        <h1>Loans &amp; advances</h1>
        <p>Request a salary advance or staff loan, and follow your repayments.</p>
    </div>
    <div class="pt-stat">Outstanding<b>K{{ number_format((float) $loans->where('status', 'active')->sum('balance'), 2) }}</b></div>
</div>

<div class="pt-grid">
    <div class="pt-card pt-span-5" id="apply">
        <h3>New loan request</h3>
        <form method="POST" action="{{ route('portal.loans.store') }}" enctype="multipart/form-data" novalidate>
            @csrf
            <div class="zp-field">
                <label class="zp-label" for="amount">How much do you need? (ZMW)</label>
                <input class="zp-input @error('amount') is-invalid @enderror" type="number" step="0.01" min="1" name="amount" id="amount" value="{{ old('amount') }}" required placeholder="2,500.00">
                @error('amount')<div class="zp-error">{{ $message }}</div>@enderror
            </div>
            <div class="zp-field">
                <label class="zp-label" for="payment_plan">How should it be repaid?</label>
                <select class="zp-select" name="payment_plan" id="payment_plan">
                    @foreach($plans as $k => $label)<option value="{{ $k }}" @selected(old('payment_plan', 'monthly') === $k)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="zp-field" id="installmentField">
                <label class="zp-label" for="installment_amount">Amount per deduction (ZMW)</label>
                <input class="zp-input @error('installment_amount') is-invalid @enderror" type="number" step="0.01" min="0.01" name="installment_amount" id="installment_amount" value="{{ old('installment_amount') }}">
                <div class="zp-hint" id="installmentHint"></div>
                @error('installment_amount')<div class="zp-error">{{ $message }}</div>@enderror
            </div>
            <div class="zp-field" id="planNoteField" hidden>
                <label class="zp-label" for="payment_plan_note">Describe the repayment plan</label>
                <input class="zp-input @error('payment_plan_note') is-invalid @enderror" name="payment_plan_note" id="payment_plan_note" value="{{ old('payment_plan_note') }}">
                @error('payment_plan_note')<div class="zp-error">{{ $message }}</div>@enderror
            </div>
            <div class="zp-field">
                <label class="zp-label" for="reason">Reason <small>(optional)</small></label>
                <textarea class="zp-textarea" name="reason" id="reason" rows="3">{{ old('reason') }}</textarea>
            </div>
            <div class="zp-field">
                <label class="zp-label" for="documents">Supporting documents <small>(optional)</small></label>
                <input class="zp-input" type="file" name="documents[]" id="documents" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
            </div>
            <button class="zp-btn zp-btn-copper zp-btn-block" type="submit"><i class="bi bi-send"></i> Send loan request</button>
        </form>
    </div>

    <div class="pt-span-7" style="display:flex;flex-direction:column;gap:1.25rem">
        <div class="pt-card">
            <h3>My loans</h3>
            @forelse($loans as $loan)
                @php $paid = $loan->principal_amount > 0 ? 1 - ($loan->balance / $loan->principal_amount) : 0; @endphp
                <div style="padding:.6rem 0;border-bottom:1px solid #EEF2EF">
                    <div class="d-flex justify-content-between align-items-center">
                        <b>K{{ number_format((float) $loan->principal_amount, 2) }} — since {{ $loan->start_date->format('M Y') }}</b>
                        <span class="pt-status {{ $loan->status }}">{{ str_replace('_', ' ', $loan->status) }}</span>
                    </div>
                    <div class="small text-muted my-1">Balance K{{ number_format((float) $loan->balance, 2) }}{{ $loan->installment_amount ? ', K' . number_format((float) $loan->installment_amount, 2) . ' per deduction' : '' }}</div>
                    <div class="zp-setup-meter" style="width:100%;background:#EEF2EF"><span style="width:{{ round($paid * 100) }}%"></span></div>
                    @if($loan->payments->isNotEmpty())
                        <details class="small mt-2"><summary style="cursor:pointer;color:var(--zp-green);font-weight:600">Repayment history ({{ $loan->payments->count() }})</summary>
                            <table class="pt-table mt-1">
                                @foreach($loan->payments as $pay)
                                    <tr><td>{{ $pay->created_at->format('d M Y') }}</td><td>{{ \App\Models\LoanPayment::TYPES[$pay->type] ?? $pay->type }}</td><td class="money">K{{ number_format((float) $pay->amount, 2) }}</td><td class="money text-muted">K{{ number_format((float) $pay->balance_after, 2) }} left</td></tr>
                                @endforeach
                            </table>
                        </details>
                    @endif
                </div>
            @empty
                <div class="pt-empty"><i class="bi bi-emoji-smile"></i>No loans on record.</div>
            @endforelse
        </div>

        <div class="pt-card">
            <h3>My requests</h3>
            @include('portal._request-list', ['items' => $items, 'icon' => 'bi-cash-coin', 'empty' => 'No loan requests yet.'])
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const plan = document.getElementById('payment_plan'), amt = document.getElementById('amount'), inst = document.getElementById('installment_amount');
    function upd() {
        document.getElementById('installmentField').hidden = plan.value === 'once_off';
        document.getElementById('planNoteField').hidden = plan.value !== 'other';
        const a = +amt.value, i = +inst.value;
        const hint = document.getElementById('installmentHint');
        if (a > 0 && i > 0 && plan.value !== 'once_off') {
            const n = Math.ceil(a / i);
            const every = plan.value === 'bi_monthly' ? 2 : 1;
            hint.textContent = `About ${n} deduction${n > 1 ? 's' : ''} — fully repaid in roughly ${n * every} month${n * every > 1 ? 's' : ''}.`;
        } else hint.textContent = plan.value === 'once_off' ? '' : 'We\'ll show how long repayment takes.';
    }
    [plan, amt, inst].forEach(el => el.addEventListener('input', upd));
    upd();
})();
</script>
@endpush
