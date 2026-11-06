@extends('layouts.portal')
@section('title', 'My documents')
@include('portal.documents._styles')
@section('content')
@php
    $pendingContracts = $contracts->where('status', 'sent');
    $toAck = $policies->filter(function ($p) use ($acked) { $v = \App\Services\Compliance\Policies::ackVersion($p); return $v && (($acked[$p->id] ?? 0) < $v); });
@endphp
<div class="pt-page-head"><div><h1>My documents</h1><p>Your employment contract, company policies and documents shared with you.</p></div></div>

@if($pendingContracts->isNotEmpty() || $toAck->isNotEmpty())
<div class="pt-card" style="border-color:#efd3b5;background:#fffaf3">
    <h2 style="font-size:1.05rem;margin:0 0 .6rem"><i class="bi bi-bell" style="color:var(--zp-copper)"></i> Waiting for you</h2>
    @foreach($pendingContracts as $c)
        <a class="cc-step" href="{{ route('portal.documents.contract', $c) }}" style="margin-bottom:.4rem;color:inherit;text-decoration:none;align-items:center"><span class="n" style="background:var(--zp-copper);color:#fff"><i class="bi bi-pen"></i></span>
            <div style="flex:1"><b>Review and sign: {{ $c->title }}</b><small>Sent {{ $c->sent_at?->format('j M Y') }}</small></div><i class="bi bi-chevron-right"></i></a>
    @endforeach
    @foreach($toAck as $p)
        <a class="cc-step" href="{{ route('portal.documents.policy', $p) }}" style="margin-bottom:.4rem;color:inherit;text-decoration:none;align-items:center"><span class="n" style="background:var(--zp-copper);color:#fff"><i class="bi bi-journal-check"></i></span>
            <div style="flex:1"><b>Read and acknowledge: {{ $p->title }}</b><small>{{ ($acked[$p->id] ?? 0) ? 'Updated policy' : 'New policy' }}</small></div><i class="bi bi-chevron-right"></i></a>
    @endforeach
</div>
@endif

<h2 class="pt-year">My employment documents</h2>
<div class="pt-card" style="padding:.4rem 1.1rem">
    @forelse($contracts as $c)
        <div style="display:flex;justify-content:space-between;gap:.6rem;align-items:center;padding:.7rem 0;border-bottom:1px solid var(--zp-line)">
            <div><b>{{ $c->title }}</b><div class="small text-muted">{{ $c->reference }} · @include('compliance.partials.status', ['status' => $c->status, 'label' => $c->status === 'sent' ? 'Waiting for your signature' : ($c->status === 'in_approval' ? 'Signed by you, with the company' : $c->statusLabel())])</div></div>
            <div style="display:flex;gap:.4rem">
                <a class="zp-btn zp-btn-ghost" style="padding:.4rem .7rem" href="{{ route('portal.documents.contract', $c) }}" aria-label="Open"><i class="bi bi-eye"></i></a>
                <a class="zp-btn zp-btn-primary" style="padding:.4rem .7rem" href="{{ route('portal.documents.contract.pdf', $c) }}" aria-label="Download PDF"><i class="bi bi-download"></i></a>
            </div>
        </div>
    @empty
        <div class="pt-empty"><i class="bi bi-file-earmark-person"></i>No contracts yet. When HR sends you one, it will appear here.</div>
    @endforelse
</div>

<h2 class="pt-year">Company policies &amp; notices</h2>
<div class="pt-card" style="padding:.4rem 1.1rem">
    @forelse($policies as $p)
        @php $need = \App\Services\Compliance\Policies::ackVersion($p); $done = $need && ($acked[$p->id] ?? 0) >= $need; @endphp
        <div style="display:flex;justify-content:space-between;gap:.6rem;align-items:center;padding:.7rem 0;border-bottom:1px solid var(--zp-line)">
            <div><b>{{ $p->title }}</b><div class="small text-muted">Version {{ $p->active_version }} · {{ optional($p->published_at)->format('j M Y') }}</div></div>
            <div style="display:flex;gap:.4rem;align-items:center">
                @if($need)<span class="cc-pill {{ $done ? 'green' : 'amber' }}">{{ $done ? 'Acknowledged' : 'To acknowledge' }}</span>@endif
                <a class="zp-btn zp-btn-ghost" style="padding:.4rem .7rem" href="{{ route('portal.documents.policy', $p) }}">Read</a>
            </div>
        </div>
    @empty
        <div class="pt-empty"><i class="bi bi-journal"></i>No company policies have been published yet.</div>
    @endforelse
</div>

@if($shared->isNotEmpty())
<h2 class="pt-year">Shared with you</h2>
<div class="pt-card" style="padding:.4rem 1.1rem">
    @foreach($shared as $d)
        <div style="display:flex;justify-content:space-between;gap:.6rem;align-items:center;padding:.7rem 0;border-bottom:1px solid var(--zp-line)">
            <div><b>{{ $d->title }}</b><div class="small text-muted">{{ strtoupper($d->latestVersion?->extension) }} · {{ $d->updated_at->format('j M Y') }}</div></div>
            <div style="display:flex;gap:.4rem">
                @if($d->latestVersion?->previewable())<a class="zp-btn zp-btn-ghost" style="padding:.4rem .7rem" href="{{ route('portal.documents.file', [$d, 'inline' => 1]) }}" target="_blank" aria-label="Open"><i class="bi bi-eye"></i></a>@endif
                <a class="zp-btn zp-btn-primary" style="padding:.4rem .7rem" href="{{ route('portal.documents.file', $d) }}" aria-label="Download"><i class="bi bi-download"></i></a>
            </div>
        </div>
    @endforeach
</div>
@endif
@endsection
