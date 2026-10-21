@extends('layouts.app')

@section('title', 'Change requests · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-requests.css') }}">
@endpush

@php
    $tabs = ['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'all' => 'All'];
    $mask = fn ($v) => $v ? '••••' . substr((string) $v, -4) : '—';
@endphp

@section('content')
<div class="pr pp rq">
    <div class="pr-head">
        <div>
            <h1>Change requests</h1>
            <p>Bank details employees asked to change in the portal. Confirm with the person (in person, or on a phone number you already have) before approving.</p>
        </div>
    </div>

    <div class="rq-bar">
        <nav class="rq-tabs">
            @foreach($tabs as $key => $label)
                <a href="{{ route('employees.changes.index', ['status' => $key]) }}" class="rq-tab {{ $status === $key ? 'on' : '' }}">{{ $label }} @if(isset($counts[$key]))<span class="n">{{ $counts[$key] }}</span>@endif</a>
            @endforeach
        </nav>
    </div>

    <div class="rq-list">
        @forelse($changes as $c)
            @php $emp = $c->employee; @endphp
            <article class="rq-item {{ $c->status === 'pending' ? 'attn' : '' }}">
                <div class="rq-av">{{ $emp?->initials }}</div>
                <div class="rq-main">
                    <div class="rq-who"><b>{{ $emp?->full_name }}</b><small>{{ $emp?->employee_id }}</small><span class="rq-type">{{ $c->typeLabel() }}</span></div>
                    <table style="margin-top:.5rem;font-size:.86rem;border-collapse:collapse">
                        @foreach(\App\Models\EmployeeChangeRequest::FIELDS[$c->type] ?? [] as $field => $label)
                            <tr>
                                <td style="color:var(--zp-ink-3);padding:.15rem 1rem .15rem 0">{{ $label }}</td>
                                <td style="padding:.15rem 0">{{ $field === 'bank_account_no' ? $mask($c->previous[$field] ?? null) : ($c->previous[$field] ?? '—') }}
                                    <i class="bi bi-arrow-right" style="color:var(--zp-ink-3)"></i>
                                    <b>{{ $field === 'bank_account_no' && $c->status !== 'pending' ? $mask($c->changes[$field] ?? null) : ($c->changes[$field] ?? '—') }}</b></td>
                            </tr>
                        @endforeach
                    </table>
                    @if($c->note)<div class="rq-note">“{{ $c->note }}”</div>@endif
                    @if($c->documents)<div class="rq-docs"><a href="{{ route('employees.changes.document', $c) }}"><i class="bi bi-paperclip"></i> {{ $c->documents[0]['name'] ?? 'Document' }}</a></div>@endif
                    <div class="rq-trail">
                        <span><i class="bi bi-send"></i>Asked {{ $c->created_at->diffForHumans() }}</span>
                        @if($c->reviewed_at)<span><i class="bi bi-check2-circle"></i>{{ ucfirst($c->status) }} by {{ $c->reviewedBy?->name }} · {{ $c->reviewed_at->format('j M Y') }}</span>@endif
                        @if($c->review_comment)<span><i class="bi bi-chat-left-text"></i>{{ $c->review_comment }}</span>@endif
                    </div>
                </div>
                <div class="rq-side">
                    <span class="rq-pill {{ $c->status }}">{{ $c->statusLabel() }}</span>
                    @if($c->status === 'pending' && (int) auth()->user()->employee_id !== (int) $c->employee_id)
                        <form method="POST" action="{{ route('employees.changes.approve', $c) }}" class="rq-decide" style="flex-direction:column;align-items:flex-end">
                            @csrf
                            <label style="font-size:.8rem;display:flex;gap:.4rem;align-items:center"><input type="checkbox" name="confirmed" value="1" required> I checked this with {{ $emp?->first_name }}</label>
                            <input class="pp-input" type="text" name="comment" maxlength="500" placeholder="Comment (optional)">
                            <div style="display:flex;gap:.35rem">
                                <button class="pp-btn pp-btn-sm rq-btn-no" formaction="{{ route('employees.changes.reject', $c) }}" formnovalidate><i class="bi bi-x-lg"></i> Reject</button>
                                <button class="pp-btn pp-btn-sm pp-btn-primary"><i class="bi bi-check-lg"></i> Approve &amp; update</button>
                            </div>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="rq-empty"><i class="bi bi-shield-check"></i>No change requests here.</div>
        @endforelse
    </div>
    <div style="margin-top:1rem">{{ $changes->links() }}</div>
</div>
@endsection
