@extends('emails.layouts.brand')

@php $c = $change; $e = $c->employee; $mask = fn ($v) => $v ? '••••' . substr((string) $v, -4) : '—'; @endphp

@section('title', 'Bank details change')
@section('eyebrow', 'Bank details')
@section('heading')
@if($kind === 'requested')Bank details change requested@elseif($kind === 'hr'){{ $e?->full_name }} asked to change their bank details@else Your bank details change was {{ $c->status }}@endif
@endsection

@section('body')
@if($kind === 'requested')
    <p>Hi {{ $e?->first_name }}, someone asked from your ZamPayroll portal to change the bank account your salary is paid into. HR checks it before anything changes.</p>
    <p style="background:#FBF0E5;border-radius:10px;padding:12px 14px;color:#7a4513"><b>Wasn't you?</b> Tell HR straight away and change your portal password.</p>
@elseif($kind === 'hr')
    <p>Please confirm it with them before approving: in person, or by phone on a number you already have (not one from the request).</p>
@elseif($c->review_comment)
    <p>HR: “{{ $c->review_comment }}”</p>
@endif
<table style="width:100%;font-size:14px;border-collapse:collapse;margin-top:8px">
    @foreach(\App\Models\EmployeeChangeRequest::FIELDS[$c->type] ?? [] as $field => $label)
        <tr>
            <td style="padding:6px 0;color:#7B877F">{{ $label }}</td>
            <td style="padding:6px 0;text-align:right">{{ $field === 'bank_account_no' ? $mask($c->previous[$field] ?? null) : ($c->previous[$field] ?? '—') }} → <b>{{ $field === 'bank_account_no' ? $mask($c->changes[$field] ?? null) : ($c->changes[$field] ?? '—') }}</b></td>
        </tr>
    @endforeach
</table>
@endsection
