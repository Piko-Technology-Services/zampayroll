@extends('layouts.app')
@section('title', $contract->reference . ' · ZamPayroll')
@include('compliance.partials.head')
@section('content')
@php $can = auth()->user()->hasPermission('compliance.contracts'); $round = $approvals->where('round', $contract->round); @endphp
<div class="pr pp cc">
    <div class="pr-head"><div><h1>{{ $contract->reference }} · {{ $contract->employee->full_name }}</h1>
        <p>{{ \App\Services\Compliance\TemplateLibrary::CONTRACT_TYPES[$contract->contract_type] ?? '' }} · version {{ $contract->current_version }} · @include('compliance.partials.status', ['status' => $contract->status, 'label' => $contract->statusLabel()])</p></div>
        <div class="pr-head-actions">
            <a class="pp-btn pp-btn-ghost" href="{{ route('compliance.contracts.preview', $contract) }}" target="_blank"><i class="bi bi-eye"></i> {{ $contract->isExecuted() ? 'Open signed PDF' : 'Preview PDF' }}</a>
            <a class="pp-btn pp-btn-ghost" href="{{ route('compliance.contracts.pdf', $contract) }}"><i class="bi bi-download"></i> PDF</a>
            @if($can)<a class="pp-btn pp-btn-ghost" href="{{ route('compliance.contracts.docx', $contract) }}"><i class="bi bi-file-earmark-word"></i> Word</a>@endif
            @if($can && $contract->isEditable())<a class="pp-btn pp-btn-primary" href="{{ route('compliance.contracts.edit', $contract) }}"><i class="bi bi-pencil"></i> Edit{{ in_array($contract->status, ['declined', 'changes', 'rejected']) ? ' and resend' : '' }}</a>@endif
            @if($can && $contract->isExecuted())<form method="POST" action="{{ route('compliance.contracts.amend', $contract) }}" onsubmit="return confirm('Start an amendment? The executed contract stays valid until the amendment is fully signed.')">@csrf<button class="pp-btn pp-btn-primary"><i class="bi bi-file-earmark-diff"></i> Create amendment</button></form>@endif
        </div></div>

    @if($missing)<div class="cc-alert warn"><i class="bi bi-exclamation-triangle lead"></i><div>Missing before it can be sent: {{ implode(', ', array_map([\App\Services\Compliance\Placeholders::class, 'label'], $missing)) }}.</div><a class="go" href="{{ route('compliance.contracts.edit', $contract) }}">Complete</a></div>@endif
    @if(in_array($contract->status, ['declined', 'changes', 'rejected']))
        <div class="cc-alert danger"><i class="bi bi-arrow-return-left lead"></i><div>
            @if($contract->status === 'declined')<b>Declined by the employee:</b> {{ $contract->decline_reason }}@else
                @php $back = $approvals->whereIn('status', ['rejected', 'changes'])->last(); @endphp
                <b>{{ $back?->acted_by_name }} ({{ $back?->label }}):</b> {{ $back?->comment }}@endif
            <br><small>Edit the contract to create a new version, then send it again. The employee signs again and approvals restart.</small></div></div>
    @endif
    @if($contract->supersedes)<div class="cc-note" style="margin-bottom:1rem">This is an amendment of <a href="{{ route('compliance.contracts.show', $contract->supersedes) }}">{{ $contract->supersedes->reference }}</a>.</div>@endif

    <div class="pr-layout">
        <div>
            <div class="cc-paper scroll">{!! $body !!}</div>
        </div>
        <aside class="pr-side">
            <section class="pr-card">
                <h2 style="font-size:1.05rem;margin-bottom:.6rem">Progress</h2>
                <div class="cc-steps">
                    <div class="cc-step {{ $contract->sent_at ? 'done' : 'now' }}"><span class="n">1</span><div><b>Prepared and sent</b><small>{{ $contract->sent_at ? 'Sent ' . $contract->sent_at->format('j M Y H:i') : 'Draft' }}</small></div></div>
                    <div class="cc-step {{ $contract->employee_signed_at ? 'done' : ($contract->status === 'sent' ? 'now' : ($contract->status === 'declined' ? 'bad' : '')) }}"><span class="n">2</span><div><b>Employee review and signature</b>
                        <small>{{ $contract->employee_signed_at ? 'Signed ' . $contract->employee_signed_at->format('j M Y H:i') : ($contract->status === 'declined' ? 'Declined' : ($contract->employee_viewed_at ? 'Opened ' . $contract->employee_viewed_at->format('j M Y H:i') : 'Not opened yet')) }}</small></div></div>
                    @forelse($round as $a)
                        <div class="cc-step {{ $a->status === 'approved' ? 'done' : ($a->status === 'pending' ? 'now' : (in_array($a->status, ['rejected', 'changes']) ? 'bad' : '')) }}"><span class="n">{{ $loop->iteration + 2 }}</span>
                            <div><b>{{ $a->label }}</b><small>{{ $a->approverLabel() }} · {{ \App\Models\Compliance\ApprovalRequest::STATUSES[$a->status] }}{{ $a->acted_at ? ' ' . $a->acted_at->format('j M Y H:i') . ' by ' . $a->acted_by_name : '' }}</small></div></div>
                    @empty
                        @if($workflow)@foreach($workflow->steps as $st)<div class="cc-step"><span class="n">{{ $loop->iteration + 2 }}</span><div><b>{{ $st->label }}</b><small>{{ $st->approver_type === 'user' ? $st->approver?->name : 'Role: ' . $st->approver_role_key }}</small></div></div>@endforeach
                        @else<p class="cc-note">No contract approval workflow yet. <a href="{{ route('compliance.workflows.create') }}">Set one up</a>.</p>@endif
                    @endforelse
                    <div class="cc-step {{ $contract->isExecuted() ? 'done' : '' }}"><span class="n"><i class="bi bi-check2"></i></span><div><b>Fully executed</b><small>{{ $contract->executed_at ? $contract->executed_at->format('j M Y H:i') : 'Not yet' }}</small></div></div>
                </div>
                @if($contract->executed_hash)<p class="cc-note" style="margin-top:.7rem;word-break:break-all"><b>PDF fingerprint (SHA-256)</b><br><code style="font-size:.72rem">{{ $contract->executed_hash }}</code></p>@endif
                @if($can && ! in_array($contract->status, ['executed', 'superseded', 'cancelled']))
                    <form method="POST" action="{{ route('compliance.contracts.cancel', $contract) }}" onsubmit="return confirm('Cancel this contract? It stays on record.')" style="margin-top:.7rem">@csrf<button class="pp-btn pp-btn-ghost pp-btn-sm" style="color:var(--zp-red)">Cancel contract</button></form>
                @endif
            </section>

            <section class="pr-card">
                <h2 style="font-size:1.05rem;margin-bottom:.5rem">Questions and comments</h2>
                @forelse($comments as $cm)
                    <div class="cc-note" style="margin-bottom:.4rem"><b>{{ $cm->author_name }}</b> <small class="pp-muted">({{ $cm->author_role === 'employee' ? 'employee' : 'HR' }}) · {{ $cm->created_at->diffForHumans() }}</small><br>{{ $cm->body }}</div>
                @empty<small class="pp-muted">None yet.</small>@endforelse
                @if($can)<form method="POST" action="{{ route('compliance.contracts.comment', $contract) }}" style="display:grid;gap:.4rem;margin-top:.5rem">@csrf
                    <textarea class="pp-input" name="body" rows="2" required maxlength="2000" placeholder="Reply or add a note"></textarea><button class="pp-btn pp-btn-ghost pp-btn-sm">Add comment</button></form>@endif
            </section>

            <section class="pr-card">
                <h2 style="font-size:1.05rem;margin-bottom:.5rem">History</h2>
                <ul class="cc-timeline">@foreach($history as $h)<li><i class="bi bi-dot"></i><div>{{ $h->summary }}<small>{{ $h->user_name ?? 'ZamPayroll' }} · {{ $h->created_at->format('j M Y H:i') }}</small></div></li>@endforeach</ul>
                <h3 style="font-size:.9rem;margin:.8rem 0 .4rem">Versions</h3>
                <ul class="cc-timeline">@foreach($contract->versions as $v)<li><i class="bi bi-file-earmark-text"></i><div>v{{ $v->version }} {{ $v->hash ? '(sent)' : '(draft)' }}: {{ $v->change_note }}<small>{{ \Illuminate\Support\Carbon::parse($v->created_at)->format('j M Y H:i') }}</small></div></li>@endforeach</ul>
                @if($mails->isNotEmpty())
                <details style="margin-top:.8rem"><summary style="font-size:.9rem;font-weight:700;cursor:pointer">Emails ({{ $mails->count() }}{{ $mails->where('status', 'failed')->count() ? ', ' . $mails->where('status', 'failed')->count() . ' failed' : '' }})</summary>
                <ul class="cc-timeline" style="margin-top:.4rem">@foreach($mails as $m)<li><i class="bi {{ $m->status === 'sent' ? 'bi-envelope-check' : 'bi-envelope-x' }}" style="{{ $m->status === 'failed' ? 'color:var(--zp-red)' : '' }}"></i><div>{{ str_replace('.', ' ', $m->event) }} → {{ $m->recipient }}<small>{{ $m->status === 'sent' ? 'Sent' : 'Failed: ' . \Illuminate\Support\Str::limit($m->error, 80) }} · {{ \Illuminate\Support\Carbon::parse($m->created_at)->format('j M H:i') }}</small></div></li>@endforeach</ul></details>
                @endif
            </section>
        </aside>
    </div>
</div>
@endsection
