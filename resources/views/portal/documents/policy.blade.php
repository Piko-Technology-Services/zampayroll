@extends('layouts.portal')
@section('title', $policy->title)
@include('portal.documents._styles')
@section('content')
<div class="pp cc">
<div class="pt-page-head"><div><h1>{{ $policy->title }}</h1><p>Version {{ $version->version }} · published {{ optional($version->published_at)->format('j M Y') }}</p></div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a class="zp-btn zp-btn-ghost" href="{{ route('portal.documents.index') }}"><i class="bi bi-arrow-left"></i> My documents</a>
        <a class="zp-btn zp-btn-primary" href="{{ route('portal.documents.policy.pdf', $policy) }}"><i class="bi bi-download"></i> Download</a>
    </div></div>
<div class="cc-paper" style="margin-bottom:1rem">{!! $version->body !!}</div>
@if($needsAck)
<div class="pt-card" id="ack">
    <h2 style="font-size:1.1rem;margin:0 0 .4rem">Acknowledge this policy</h2>
    <form method="POST" action="{{ route('portal.documents.policy.acknowledge', $policy) }}" style="display:grid;gap:.7rem">@csrf
        <input type="hidden" name="version" value="{{ $version->version }}"><input type="hidden" name="hash" value="{{ $version->hash }}">
        <label class="pp-toggle" style="align-items:flex-start"><input type="checkbox" name="confirm" value="1" required> <span>{{ $statement }}</span></label>
        @error('confirm')<small style="color:var(--zp-red)">{{ $message }}</small>@enderror
        @error('acknowledge')<small style="color:var(--zp-red)">{{ $message }}</small>@enderror
        <div><button class="pp-btn pp-btn-primary"><i class="bi bi-check2-circle"></i> I acknowledge</button></div>
    </form>
</div>
@elseif($mine)
    <div class="cc-alert info"><i class="bi bi-check2-circle lead"></i><div>You acknowledged version {{ $mine->version }} on {{ $mine->acknowledged_at->format('j M Y H:i') }}.</div></div>
@endif
</div>
@endsection
