@extends('layouts.app')

@section('title', $ticket->subject . ' · Help')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-billing.css') }}">
@endpush

@section('content')
<div class="pr pp">
    <a class="pr-crumb" href="{{ route('support.index') }}"><i class="bi bi-arrow-left"></i> Help</a>
    <div class="pr-head">
        <div><h1>{{ $ticket->subject }}</h1>
            <p>{{ $ticket->number }} · {{ \App\Models\SupportTicket::CATEGORIES[$ticket->category] ?? $ticket->category }} ·
                <span class="pr-pill {{ $ticket->isOpen() ? 'review' : 'locked' }}">{{ \App\Models\SupportTicket::STATUSES[$ticket->status] ?? $ticket->status }}</span></p></div>
        @if($ticket->isOpen())
            <form method="POST" action="{{ route('support.close', $ticket) }}">@csrf<button class="pp-btn pp-btn-ghost pp-btn-sm"><i class="bi bi-check2-circle"></i> Mark as solved</button></form>
        @endif
    </div>
    <section class="pr-card">
        <div class="tk-thread">
            @foreach($ticket->messages as $m)
                <div class="tk-msg {{ $m->from_staff ? 'staff' : 'me' }}">
                    <div class="who">{{ $m->from_staff ? 'ZamPayroll support' : $m->author_name }} · {{ $m->created_at->format('j M, H:i') }}</div>
                    <div class="body">{{ $m->body }}</div>
                    @foreach($m->attachments ?? [] as $a)<div class="att"><i class="bi bi-paperclip"></i> {{ $a['name'] }}</div>@endforeach
                </div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('support.reply', $ticket) }}" enctype="multipart/form-data" style="margin-top:1rem;display:grid;gap:.6rem">
            @csrf
            <textarea class="pp-input" name="body" rows="3" required placeholder="Write a reply…"></textarea>
            <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap"><input type="file" name="attachments[]" multiple class="pp-input" style="flex:1;min-width:200px"><button class="pp-btn pp-btn-primary"><i class="bi bi-send"></i> Reply</button></div>
        </form>
    </section>
</div>
<style>
.tk-thread{display:grid;gap:.8rem}
.tk-msg{max-width:80%;padding:.8rem 1rem;border-radius:16px;background:var(--zp-mist)}
.tk-msg.me{justify-self:end;background:var(--zp-green-50)}
.tk-msg.staff{justify-self:start;background:#fff;border:1px solid var(--zp-line)}
.tk-msg .who{font-size:.74rem;color:var(--zp-ink-3);font-weight:600;margin-bottom:.25rem}
.tk-msg .body{white-space:pre-line;font-size:.9rem}
.tk-msg .att{font-size:.78rem;color:var(--zp-ink-2);margin-top:.3rem}
@media (max-width:720px){.tk-msg{max-width:100%}}
</style>
@endsection
