@extends('platform.layout')
@section('title', 'Billing · ZamPayroll admin')
@php $k = fn ($n) => 'K ' . number_format((float) $n, 2); @endphp
@section('content')
<div class="pr-head"><div><h1>Billing</h1><p>All invoices and payments across every company.</p></div></div>
<div class="pl-filter">
    @foreach(['' => 'All', 'open' => 'Open', 'overdue' => 'Overdue', 'paid' => 'Paid', 'void' => 'Void'] as $v => $l)
        <a href="{{ route('platform.invoices', array_filter(['status' => $v])) }}" class="{{ (string) $status === $v ? 'on' : '' }}">{{ $l }}</a>
    @endforeach
</div>
<section class="pr-card" style="margin-bottom:16px">
    <div class="pr-card-head"><div><h2>Payment proofs to check</h2><div class="sub">Manual payments sent by companies. Check the money has arrived, then confirm it or decline it with a reason.</div></div></div>
    @forelse($proofs as $p)
        <div class="pl-row" style="align-items:flex-start;gap:14px;padding:14px 0;border-top:1px solid var(--line, #e7e2d6)">
            <i class="bi bi-file-earmark-check" style="font-size:1.4rem"></i>
            <div class="grow">
                <b>{{ $p->company?->name }} · {{ $k($p->amount) }}</b>
                <small>{{ $p->invoice?->number }} · {{ $p->invoice?->title() }} · sent {{ $p->created_at->format('j M Y H:i') }}{{ $p->reference ? ' · ref: ' . $p->reference : '' }}</small>
                <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                    <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('platform.proofs.file', $p) }}" target="_blank"><i class="bi bi-paperclip"></i> View proof</a>
                    <form method="POST" action="{{ route('platform.proofs.confirm', $p) }}" onsubmit="return confirm('Confirm this payment has arrived? The plan will be activated.')">
                        @csrf
                        <button class="pp-btn pp-btn-primary pp-btn-sm"><i class="bi bi-check2-circle"></i> Confirm receipt</button>
                    </form>
                    <form method="POST" action="{{ route('platform.proofs.decline', $p) }}" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                        @csrf
                        <input name="reason" class="pp-input" placeholder="Reason for declining" required minlength="5" maxlength="500" style="min-width:260px">
                        <button class="pp-btn pp-btn-ghost pp-btn-sm"><i class="bi bi-x-circle"></i> Decline</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="pr-empty"><i class="bi bi-inbox"></i>No proofs waiting for review.</div>
    @endforelse
</section>
<div class="pr-layout" style="grid-template-columns:minmax(0,1fr) 380px">
    <section class="pr-card">
        <div class="pr-tablewrap"><table class="pr-table pr-hist">
            <thead><tr><th>Invoice</th><th>Company</th><th>Status</th><th style="text-align:right">Total</th><th>Due</th></tr></thead>
            <tbody>
            @forelse($invoices as $inv)
                <tr onclick="location.href='{{ route('platform.companies.show', $inv->company_id) }}'">
                    <td><b>{{ $inv->number }}</b><br><small class="pp-muted">{{ $inv->title() }}</small></td>
                    <td>{{ $inv->company?->name }}</td>
                    <td><span class="pl-tag {{ $inv->status === 'paid' ? 'active' : ($inv->isOverdue() ? 'suspended' : 'trialing') }}">{{ $inv->isOverdue() ? 'overdue' : $inv->status }}</span></td>
                    <td class="num"><b>{{ $k($inv->total) }}</b></td>
                    <td>{{ optional($inv->due_at)->format('j M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="pr-empty"><i class="bi bi-receipt"></i>No invoices.</div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $invoices->links('pagination::bootstrap-5') }}
    </section>
    <section class="pr-card">
        <div class="pr-card-head"><h2>Latest payment attempts</h2></div>
        <div class="pl-list">
            @foreach($payments as $p)
                <a class="pl-row" href="{{ route('platform.companies.show', $p->company_id) }}"><i class="bi {{ $p->method === 'card' ? 'bi-credit-card' : ($p->provider === 'manual' ? 'bi-bank' : 'bi-phone') }}"></i>
                    <div class="grow"><b>{{ $p->company?->name }} · {{ $k($p->amount) }}</b><small>{{ $p->invoice?->number }} · {{ $p->created_at->format('j M H:i') }}{{ $p->result_explanation ? ' · ' . $p->result_explanation : '' }}</small></div>
                    <span class="pl-tag {{ $p->status === 'paid' ? 'active' : ($p->status === 'pending' ? 'trialing' : 'suspended') }}">{{ $p->status }}</span></a>
            @endforeach
        </div>
    </section>
</div>
@endsection
