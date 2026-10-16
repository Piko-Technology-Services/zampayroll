@extends('emails.layouts.brand')

@php
    $name = $run->displayName();
    $k = fn ($n) => 'K ' . number_format((float) $n, 2);
@endphp

@section('title', $name)
@section('preheader', $kind === 'review' ? "{$name} is waiting for your approval." : ($kind === 'sent_back' ? "{$name} was sent back with comments." : "{$name} is approved and locked."))
@section('eyebrow', 'Payroll')
@section('heading', $kind === 'review' ? 'A payroll needs your approval' : ($kind === 'sent_back' ? 'Changes requested' : 'Payroll approved'))

@section('body')
    <p style="margin:0 0 14px;">Hi {{ \Illuminate\Support\Str::before($recipient->name, ' ') }},</p>
    @if($kind === 'review')
        <p style="margin:0 0 14px;"><b>{{ $name }}</b> is ready for the <b>{{ $step['name'] ?? 'approval' }}</b> step.</p>
    @elseif($kind === 'sent_back')
        <p style="margin:0 0 14px;">{{ $actor?->name ?? 'An approver' }} sent <b>{{ $name }}</b> back for changes.</p>
        @if($comment)<p style="margin:0 0 14px;padding:12px 16px;background:#FBF0E5;border-radius:10px;">“{{ $comment }}”</p>@endif
    @else
        <p style="margin:0 0 14px;"><b>{{ $name }}</b> has been fully approved and is now locked. You can send payslips and download the bank file.</p>
    @endif
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EAF3EA;border-radius:12px;margin:0 0 10px;">
        <tr><td style="padding:14px 18px;font-size:14px;color:#0A3B22;">
            {{ $run->payrolls()->count() }} people · Take-home <b>{{ $k($run->net_pay) }}</b> · Gross {{ $k($run->total_income) }}
        </td></tr>
    </table>
    @include('emails.partials.button', ['url' => route('payroll.runs.show', $run), 'label' => $kind === 'review' ? 'Review & approve' : 'Open the payroll'])
@endsection

@section('footer_note')
    You're receiving this because you're part of your company's payroll approval chain.
@endsection
