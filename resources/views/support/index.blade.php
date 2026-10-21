@extends('layouts.app')

@section('title', 'Help · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/zp-payroll.css') }}">
@endpush

@section('content')
<div class="pr pp">
    <div class="pr-head">
        <div><h1>Help &amp; support</h1><p>Ask a question or report a problem. A real person at ZamPayroll replies — usually within one working day.</p></div>
        <div class="pr-head-actions">
            <a class="pp-btn pp-btn-ghost" href="https://wa.me/{{ config('site.contact.whatsapp', '260776136965') }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp us</a>
        </div>
    </div>
    <div class="pr-layout">
        <section class="pr-card">
            <div class="pr-card-head"><h2>Your tickets</h2></div>
            @forelse($tickets as $t)
                <a href="{{ route('support.show', $t) }}" class="pf-item" style="margin-bottom:.5rem;color:inherit;display:flex;gap:.8rem;align-items:center;padding:.8rem .9rem;border:1px solid var(--zp-line);border-radius:14px">
                    <span class="pr-av" style="width:40px;height:40px"><i class="bi {{ $t->last_reply_by_staff && $t->isOpen() ? 'bi-chat-dots-fill' : 'bi-chat-left-text' }}"></i></span>
                    <div style="flex:1;min-width:0">
                        <b style="display:block">{{ $t->subject }}</b>
                        <small class="pp-muted">{{ $t->number }} · {{ $categories[$t->category] ?? $t->category }} · {{ $t->messages_count }} {{ \Illuminate\Support\Str::plural('message', $t->messages_count) }} · {{ optional($t->last_reply_at)->diffForHumans() }}</small>
                    </div>
                    <span class="pr-pill {{ $t->status === 'waiting' ? 'review' : ($t->isOpen() ? 'draft' : 'locked') }}">{{ \App\Models\SupportTicket::STATUSES[$t->status] ?? $t->status }}</span>
                </a>
            @empty
                <div class="pr-empty"><i class="bi bi-life-preserver"></i>No tickets yet. We're here when you need us.</div>
            @endforelse
        </section>
        <aside class="pr-side">
            <section class="pr-card">
                <div class="pr-card-head" style="margin-bottom:.6rem"><h2 style="font-size:1.05rem">New ticket</h2></div>
                <form method="POST" action="{{ route('support.store') }}" enctype="multipart/form-data" style="display:grid;gap:.7rem">
                    @csrf
                    <div class="pp-field"><label>What's it about?</label>
                        <select class="pp-select" name="category">@foreach($categories as $k => $l)<option value="{{ $k }}" @selected(old('category', request('category')) === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="pp-field"><label for="subject">Subject</label><input class="pp-input" id="subject" name="subject" value="{{ old('subject') }}" required maxlength="160" placeholder="e.g. PAYE looks wrong for one employee"></div>
                    <div class="pp-field"><label for="body">Details</label><textarea class="pp-input" id="body" name="body" rows="5" required placeholder="Tell us what happened and what you expected.">{{ old('body') }}</textarea></div>
                    <div class="pp-field"><label>Screenshot or file <small>optional</small></label><input type="file" name="attachments[]" multiple class="pp-input"></div>
                    <label class="pp-toggle"><input type="checkbox" name="priority" value="urgent"> It's urgent (payroll is due)</label>
                    <button class="pp-btn pp-btn-primary"><i class="bi bi-send"></i> Send to support</button>
                </form>
            </section>
        </aside>
    </div>
</div>
@endsection
