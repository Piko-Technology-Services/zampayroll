@extends('platform.layout')
@section('title', 'Tickets · ZamPayroll admin')
@section('content')
<div class="pr-head"><div><h1>Support tickets</h1><p>Questions and problems from client companies.</p></div></div>
<div class="pl-filter">
    @foreach(['open' => 'Open', 'resolved' => 'Resolved', 'closed' => 'Closed', 'all' => 'All'] as $v => $l)
        <a href="{{ route('platform.tickets', ['status' => $v]) }}" class="{{ $status === $v ? 'on' : '' }}">{{ $l }}</a>
    @endforeach
</div>
<section class="pr-card">
    <div class="pl-list">
        @forelse($tickets as $t)
            <a class="pl-row" href="{{ route('platform.tickets.show', $t) }}">
                <i class="bi {{ $t->last_reply_by_staff ? 'bi-reply' : 'bi-chat-left-dots-fill' }}" style="color:{{ $t->last_reply_by_staff ? 'var(--zp-ink-3)' : 'var(--zp-copper)' }}"></i>
                <div class="grow"><b>{{ $t->subject }}</b><small>{{ $t->company?->name }} · {{ $t->number }} · {{ \App\Models\SupportTicket::CATEGORIES[$t->category] ?? $t->category }} · {{ $t->messages_count }} msgs · {{ optional($t->last_reply_at)->diffForHumans() }}</small></div>
                @if($t->priority === 'urgent')<span class="pl-tag urgent">urgent</span>@endif
                <span class="pl-tag">{{ $t->last_reply_by_staff ? 'waiting on client' : $t->status }}</span>
            </a>
        @empty
            <div class="pr-allclear"><i class="bi bi-inbox"></i>No tickets here.</div>
        @endforelse
    </div>
    {{ $tickets->links('pagination::bootstrap-5') }}
</section>
@endsection
