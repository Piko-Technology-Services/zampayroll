@extends('layouts.app')
@section('title', 'Review ' . $subject->reference . ' · ZamPayroll')
@include('compliance.partials.head')
@section('content')
@php $isContract = $subject instanceof \App\Models\Compliance\EmploymentContract; @endphp
<div class="pr pp cc">
    <div class="pr-head"><div><h1>{{ $subject->title }}</h1><p>{{ $isContract ? 'Contract' : 'Policy' }} {{ $subject->reference }} · version {{ $approval->version }} · Step: <b>{{ $approval->label }}</b></p></div>
        <div class="pr-head-actions">
            <a class="pp-btn pp-btn-ghost" href="{{ route('compliance.approvals.index') }}"><i class="bi bi-arrow-left"></i> Back</a>
            <a class="pp-btn pp-btn-ghost" href="{{ route('compliance.approvals.document', $approval) }}" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Review copy (PDF)</a>
        </div></div>
    <div class="pr-layout">
        <div class="cc-paper scroll">{!! $body !!}</div>
        <aside class="pr-side">
            <section class="pr-card">
                <h2 style="font-size:1.05rem;margin-bottom:.5rem">Approval steps</h2>
                <div class="cc-steps">
                    @if($isContract)<div class="cc-step done"><span class="n"><i class="bi bi-person-check"></i></span><div><b>Employee signed</b><small>{{ $subject->employee_signed_at?->format('j M Y H:i') }}</small></div></div>@endif
                    @foreach($trail as $t)
                        <div class="cc-step {{ $t->status === 'approved' ? 'done' : ($t->status === 'pending' ? 'now' : (in_array($t->status, ['rejected', 'changes']) ? 'bad' : '')) }}"><span class="n">{{ $loop->iteration }}</span>
                            <div><b>{{ $t->label }}</b><small>{{ $t->approverLabel() }} · {{ \App\Models\Compliance\ApprovalRequest::STATUSES[$t->status] }}{{ $t->acted_by_name ? ' by ' . $t->acted_by_name : '' }}</small>@if($t->comment)<small>“{{ $t->comment }}”</small>@endif</div></div>
                    @endforeach
                </div>
            </section>
            @if($canAct)
            <section class="pr-card">
                <h2 style="font-size:1.05rem;margin-bottom:.5rem">{{ $approval->action === 'sign' ? 'Sign for the company' : 'Your decision' }}</h2>
                <form method="POST" action="{{ route('compliance.approvals.decide', $approval) }}" enctype="multipart/form-data" style="display:grid;gap:.6rem" id="decideForm">
                    @csrf
                    <div class="pp-field"><label>Comment <small>required if you send it back</small></label><textarea class="pp-input" name="comment" rows="3" maxlength="2000">{{ old('comment') }}</textarea>
                        @error('comment')<small style="color:var(--zp-red)">{{ $message }}</small>@enderror</div>
                    @if($approval->action === 'sign' && $isContract)
                        <div class="pp-field"><label>Your title</label><input class="pp-input" name="signer_title" value="{{ old('signer_title', auth()->user()->employee?->position ?? $approval->label) }}" maxlength="160"></div>
                        @include('compliance.partials.signature-pad', ['id' => 'coSig', 'methodName' => 'signature_method'])
                        <label class="pp-toggle"><input type="checkbox" name="consent" value="1" required> {{ $consent }}</label>
                        @error('consent')<small style="color:var(--zp-red)">{{ $message }}</small>@enderror
                    @endif
                    <button class="pp-btn pp-btn-primary" name="decision" value="approve"><i class="bi {{ $approval->action === 'sign' ? 'bi-pen' : 'bi-check2' }}"></i> {{ $approval->action === 'sign' ? 'Sign and execute' : ($approval->action === 'review' ? 'Mark as reviewed' : 'Approve') }}</button>
                    <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                        <button class="pp-btn pp-btn-ghost pp-btn-sm" name="decision" value="changes" formnovalidate><i class="bi bi-arrow-return-left"></i> Request changes</button>
                        <button class="pp-btn pp-btn-ghost pp-btn-sm" name="decision" value="reject" formnovalidate style="color:var(--zp-red)"><i class="bi bi-x-circle"></i> Reject</button>
                    </div>
                    @if($approval->isDelegate(auth()->user()) && ! $approval->isAssignedTo(auth()->user()))<p class="cc-note">You are acting as the configured delegate for this step. This is recorded.</p>@endif
                </form>
            </section>
            @else
                <p class="cc-note">{{ $approval->status === 'pending' ? 'This step is assigned to someone else.' : 'This step is ' . strtolower(\App\Models\Compliance\ApprovalRequest::STATUSES[$approval->status]) . '.' }}</p>
            @endif
        </aside>
    </div>
</div>
@endsection
