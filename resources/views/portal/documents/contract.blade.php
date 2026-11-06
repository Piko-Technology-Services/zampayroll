@extends('layouts.portal')
@section('title', $contract->title)
@include('portal.documents._styles')
@section('content')
<div class="pp cc">
<div class="pt-page-head"><div><h1>{{ $contract->title }}</h1><p>{{ $contract->reference }} · version {{ $contract->current_version }}</p></div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a class="zp-btn zp-btn-ghost" href="{{ route('portal.documents.index') }}"><i class="bi bi-arrow-left"></i> My documents</a>
        <a class="zp-btn zp-btn-primary" href="{{ route('portal.documents.contract.pdf', $contract) }}"><i class="bi bi-download"></i> {{ $contract->status === 'executed' ? 'Download signed copy' : 'Download review copy' }}</a>
    </div></div>

@if($contract->status === 'sent')
    <div class="cc-note" style="margin-bottom:1rem"><i class="bi bi-info-circle"></i> Read the whole contract. You can ask HR a question, decline it, or accept and sign it below. Opening or downloading the contract does not mean you have accepted it.</div>
@elseif($contract->status === 'in_approval')
    <div class="cc-alert info"><i class="bi bi-hourglass-split lead"></i><div>You signed on {{ $contract->employee_signed_at?->format('j M Y H:i') }}. It is now with the company for approval and signature.</div></div>
@elseif($contract->status === 'executed')
    <div class="cc-alert info"><i class="bi bi-patch-check lead"></i><div>Fully signed on {{ $contract->executed_at?->format('j M Y') }}. Keep the signed copy for your records.</div></div>
@elseif($contract->status === 'declined')
    <div class="cc-alert warn"><i class="bi bi-x-circle lead"></i><div>You declined this contract: {{ $contract->decline_reason }}</div></div>
@else
    <div class="cc-note" style="margin-bottom:1rem">Status: {{ $contract->statusLabel() }}.</div>
@endif

<div class="cc-paper" style="margin-bottom:1rem">{!! $body !!}</div>

<div class="pt-card">
    <h2 style="font-size:1.05rem;margin:0 0 .5rem">Questions</h2>
    @forelse($comments as $cm)
        <div class="cc-note" style="margin-bottom:.4rem"><b>{{ $cm->author_role === 'employee' ? 'You' : $cm->author_name . ' (HR)' }}</b> <small class="pp-muted">{{ $cm->created_at->diffForHumans() }}</small><br>{{ $cm->body }}</div>
    @empty<p class="pp-muted" style="font-size:.9rem">No questions yet.</p>@endforelse
    @if($contract->status === 'sent')
    <form method="POST" action="{{ route('portal.documents.contract.comment', $contract) }}" style="display:grid;gap:.4rem">@csrf
        <textarea class="pp-input" name="body" rows="2" required maxlength="2000" placeholder="Ask HR about anything in the contract"></textarea>
        <div><button class="pp-btn pp-btn-ghost pp-btn-sm"><i class="bi bi-chat-left-text"></i> Send question</button></div></form>
    @endif
</div>

@if($contract->status === 'sent')
<div class="pt-card" id="sign">
    <h2 style="font-size:1.15rem;margin:0 0 .3rem">Accept and sign</h2>
    <p class="pp-muted" style="font-size:.9rem">Draw your signature or upload a photo of it. We record your signed-in account, the time and the exact version you signed.</p>
    <form method="POST" action="{{ route('portal.documents.contract.sign', $contract) }}" enctype="multipart/form-data" style="display:grid;gap:.8rem">
        @csrf
        <input type="hidden" name="version" value="{{ $version->version }}"><input type="hidden" name="hash" value="{{ $version->hash }}">
        @include('compliance.partials.signature-pad', ['id' => 'empSig', 'methodName' => 'method'])
        <div class="pp-field"><label for="typed_name">Type your full name to confirm</label><input class="pp-input" id="typed_name" name="typed_name" required maxlength="160" value="{{ old('typed_name') }}" placeholder="{{ $contract->employee->full_name }}" autocomplete="name">
            @error('typed_name')<small style="color:var(--zp-red)">{{ $message }}</small>@enderror</div>
        <label class="pp-toggle" style="align-items:flex-start"><input type="checkbox" name="consent" value="1" required> <span>{{ $consent }}</span></label>
        @error('consent')<small style="color:var(--zp-red)">{{ $message }}</small>@enderror
        <div><button class="pp-btn pp-btn-primary"><i class="bi bi-pen"></i> Sign the contract</button></div>
        <small class="pp-muted">Document fingerprint: <code>{{ substr($version->hash, 0, 16) }}…</code></small>
    </form>
    <details style="margin-top:1rem"><summary style="font-weight:700;cursor:pointer;color:var(--zp-red)">I don't accept this contract</summary>
        <form method="POST" action="{{ route('portal.documents.contract.decline', $contract) }}" style="display:grid;gap:.4rem;margin-top:.5rem">@csrf
            <textarea class="pp-input" name="reason" rows="2" required maxlength="2000" placeholder="Tell HR why, so they can respond"></textarea>
            <div><button class="pp-btn pp-btn-ghost pp-btn-sm" style="color:var(--zp-red)" onclick="return confirm('Decline this contract?')">Decline</button></div></form>
    </details>
</div>
@elseif($mySignature)
    <div class="pt-card"><h2 style="font-size:1.05rem;margin:0 0 .4rem">Your signature</h2>
        <p class="pp-muted" style="font-size:.88rem">Signed {{ $mySignature->signed_at->format('j M Y H:i') }} ({{ $mySignature->method === 'draw' ? 'drawn' : 'uploaded' }}).</p></div>
@endif
</div>
@endsection
