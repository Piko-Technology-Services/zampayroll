@extends('emails.layouts.brand')
@php $last = $ticket->messages->last(); @endphp
@section('title', $ticket->subject)
@section('eyebrow', 'Support · ' . $ticket->number)
@section('heading', $kind === 'staff_reply' ? 'ZamPayroll support replied' : $ticket->subject)
@section('body')
    <p style="margin:0 0 6px;color:#6B6F78;font-size:13px;">{{ $last?->author_name }} · {{ $ticket->company?->name }}</p>
    <div style="margin:0 0 14px;padding:14px 16px;background:#F3F6F2;border-radius:10px;white-space:pre-line;">{{ $last?->body }}</div>
    @include('emails.partials.button', ['url' => $kind === 'staff_reply' ? route('support.show', $ticket) : route('platform.tickets.show', $ticket), 'label' => 'Open the ticket'])
@endsection
