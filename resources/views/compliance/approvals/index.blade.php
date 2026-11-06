@extends('layouts.app')
@section('title', 'Approvals · ZamPayroll')
@include('compliance.partials.head')
@section('content')
<div class="pr pp cc">
    <div class="pr-head"><div><h1>Waiting for you</h1><p>Contracts and policies that your company's workflow has sent to you, or to a role you hold.</p></div></div>
    <section class="pr-card">
        @forelse($mine as $a)
            @php $s = $a->subject(); @endphp
            @continue(! $s)
            <a href="{{ route('compliance.approvals.show', $a) }}" class="cc-step" style="margin-bottom:.5rem;color:inherit;text-decoration:none;align-items:center">
                <span class="n" style="background:var(--zp-copper);color:#fff"><i class="bi {{ $a->action === 'sign' ? 'bi-pen' : 'bi-check2' }}"></i></span>
                <div style="flex:1"><b>{{ $s->title }}</b><small>{{ $a->approvable_type === 'contract' ? 'Contract ' : 'Policy ' }}{{ $s->reference }} · v{{ $a->version }} · Step: {{ $a->label }}{{ $a->isDelegate(auth()->user()) && ! $a->isAssignedTo(auth()->user()) ? ' (as delegate)' : '' }}</small></div>
                <span class="cc-pill amber">{{ $a->action === 'sign' ? 'Sign' : ($a->action === 'review' ? 'Review' : 'Approve') }}</span>
            </a>
        @empty
            <div class="pr-empty"><i class="bi bi-inbox"></i>Nothing is waiting for you.</div>
        @endforelse
    </section>
    @if($done->isNotEmpty())
    <section class="pr-card">
        <div class="pr-card-head"><h2>Your recent decisions</h2></div>
        <div class="cc-wrap"><table class="cc-table"><thead><tr><th>Document</th><th>Step</th><th>Decision</th><th>When</th></tr></thead><tbody>
        @foreach($done as $a)@php $s = $a->subject(); @endphp
            <tr><td>{{ $s?->reference }} {{ $s?->title }}</td><td>{{ $a->label }}</td><td>@include('compliance.partials.status', ['status' => $a->status, 'label' => \App\Models\Compliance\ApprovalRequest::STATUSES[$a->status]])</td><td><small>{{ $a->acted_at?->format('j M Y H:i') }}</small></td></tr>
        @endforeach
        </tbody></table></div>
    </section>
    @endif
</div>
@endsection
