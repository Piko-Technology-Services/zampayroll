@extends('platform.layout')
@section('title', $ticket->subject . ' · ZamPayroll admin')
@section('content')
<a class="pr-crumb" href="{{ route('platform.tickets') }}"><i class="bi bi-arrow-left"></i> Tickets</a>
<div class="pr-head"><div><h1>{{ $ticket->subject }}</h1>
    <p><a class="pp-link" href="{{ route('platform.companies.show', $ticket->company_id) }}">{{ $ticket->company?->name }}</a> · {{ $ticket->number }} · {{ \App\Models\SupportTicket::CATEGORIES[$ticket->category] ?? $ticket->category }} · <span class="pl-tag {{ $ticket->priority === 'urgent' ? 'urgent' : '' }}">{{ $ticket->priority }}</span> <span class="pl-tag">{{ $ticket->status }}</span></p></div></div>
<section class="pr-card">
    <div style="display:grid;gap:.8rem">
        @foreach($ticket->messages as $m)
            <div style="max-width:82%;padding:.8rem 1rem;border-radius:16px;{{ $m->from_staff ? 'justify-self:end;background:var(--zp-green-50)' : 'justify-self:start;background:#fff;border:1px solid var(--zp-line)' }}">
                <div style="font-size:.74rem;color:var(--zp-ink-3);font-weight:600;margin-bottom:.25rem">{{ $m->author_name }}{{ $m->from_staff ? ' (ZamPayroll)' : '' }} · {{ $m->created_at->format('j M, H:i') }}</div>
                <div style="white-space:pre-line;font-size:.9rem">{{ $m->body }}</div>
                @foreach($m->attachments ?? [] as $a)<div style="font-size:.78rem;margin-top:.3rem"><i class="bi bi-paperclip"></i> {{ $a['name'] }}</div>@endforeach
            </div>
        @endforeach
    </div>
    <form method="POST" action="{{ route('platform.tickets.reply', $ticket) }}" style="margin-top:1rem;display:grid;gap:.6rem">
        @csrf
        <textarea class="pp-input" name="body" rows="4" placeholder="Reply to the client (they'll get an email)…"></textarea>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center">
            <select class="pp-select" name="status" style="max-width:220px"><option value="waiting">Set: waiting for client</option><option value="resolved">Set: resolved</option><option value="open">Keep open</option><option value="closed">Close</option></select>
            <select class="pp-select" name="priority" style="max-width:160px">@foreach(['low', 'normal', 'urgent'] as $p)<option value="{{ $p }}" @selected($ticket->priority === $p)>{{ ucfirst($p) }} priority</option>@endforeach</select>
            <button class="pp-btn pp-btn-primary"><i class="bi bi-send"></i> Send / update</button>
        </div>
    </form>
</section>
@endsection
