@extends('platform.layout')
@section('title', 'Settings · ZamPayroll admin')
@section('content')
<div class="pr-head"><div><h1>Pricing &amp; billing settings</h1><p>Changes apply to new invoices. Existing invoices keep their amounts.</p></div></div>
<div class="pr-layout" style="grid-template-columns:minmax(0,1fr) 380px">
    <section class="pr-card">
        <form method="POST" action="{{ route('platform.settings.save') }}">
            @csrf
            <div class="pp-grid">
                @foreach($fields as $key => [$label, $type])
                    <div class="pp-field"><label for="{{ $key }}">{{ $label }}</label>
                        <input class="pp-input" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $values[$key]) }}" inputmode="decimal" required></div>
                @endforeach
            </div>
            <button class="pp-btn pp-btn-primary" style="margin-top:1.2rem"><i class="bi bi-check2-circle"></i> Save settings</button>
        </form>
    </section>
    <aside class="pr-side">
        <section class="pr-card">
            <div class="pr-card-head" style="margin-bottom:.6rem"><h2 style="font-size:1.05rem">DPO Pay</h2></div>
            <ul class="pr-history">
                <li class="{{ $dpo->configured() ? 'approved' : 'sent_back' }}"><i class="bi {{ $dpo->configured() ? 'bi-check-circle' : 'bi-x-circle' }}"></i><div><b>{{ $dpo->configured() ? 'Connected' : 'Not configured' }}</b><small>Mode: {{ $dpo->fake() ? 'test (fake — no real money)' : config('dpo.mode') }}</small></div></li>
                <li><i class="bi bi-key"></i><div><b>Company token</b><small>{{ config('dpo.company_token') ? '••••' . substr(config('dpo.company_token'), -4) : 'missing (DPO_COMPANY_TOKEN)' }}</small></div></li>
                <li><i class="bi bi-tag"></i><div><b>Service type</b><small>{{ config('dpo.service_type') ?: 'missing (DPO_SERVICE_TYPE)' }}</small></div></li>
                <li><i class="bi bi-link-45deg"></i><div><b>Notification URL to give DPO</b><small style="word-break:break-all">{{ route('billing.dpo.notify') }}</small></div></li>
            </ul>
            <p class="pp-muted" style="font-size:.78rem;margin:.8rem 0 0">Keys are read from the server's .env file and are never shown here in full.</p>
        </section>
        <section class="pr-card">
            <div class="pr-card-head" style="margin-bottom:.6rem"><h2 style="font-size:1.05rem">Platform admins</h2></div>
            <ul class="pr-history">@foreach($admins as $a)<li><i class="bi bi-person-gear"></i><div><b>{{ $a->name }}</b><small>{{ $a->email }}</small></div></li>@endforeach</ul>
            <p class="pp-muted" style="font-size:.78rem;margin:.6rem 0 0">Add another with <code>php artisan zampayroll:admin email@domain</code>.</p>
        </section>
    </aside>
</div>
@endsection
