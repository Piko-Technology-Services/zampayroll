{{-- ZamPayroll AI mode: floating launcher + assistant panel (HR/admin layout) --}}
@if(config('ai.enabled') && auth()->check() && auth()->user()->hasPermission('ai.use'))
@php
    $aiFirst = strtok(auth()->user()->name, ' ');
    $aiFull = \App\Services\Ai\AiAgent::aiAvailable();
    $aiMark = '<svg class="zpai-mark" viewBox="0 0 24 24" aria-hidden="true"><defs><linearGradient id="zpaiG" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2FA35A"/><stop offset=".55" stop-color="#00742D"/><stop offset=".75" stop-color="#C42926"/><stop offset="1" stop-color="#C8742B"/></linearGradient></defs><path fill="url(#zpaiG)" d="M12 1.8c.5 4.9 2.3 7.6 7.2 8.6.6.1.6.9 0 1-4.9 1-6.7 3.7-7.2 8.6-.1.6-.9.6-1 0-.5-4.9-2.3-7.6-7.2-8.6-.6-.1-.6-.9 0-1 4.9-1 6.7-3.7 7.2-8.6.1-.6.9-.6 1 0z"/><circle cx="19.5" cy="4.5" r="1.6" fill="#C8742B"/></svg>';
@endphp
<link rel="stylesheet" href="{{ asset('assets/css/zp-ai.css') }}">

<div class="zpai" id="zpai"
     data-message-url="{{ route('ai.message') }}"
     data-confirm-url="{{ url('ai/actions/__T__/confirm') }}"
     data-cancel-url="{{ url('ai/actions/__T__/cancel') }}"
     data-reset-url="{{ route('ai.reset') }}">

    <button type="button" class="zpai-fab" id="zpaiFab" aria-haspopup="dialog" aria-controls="zpaiPanel" title="AI mode (Ctrl/⌘ + K)">
        <span class="orb">{!! $aiMark !!}</span>
        <span class="lbl">Ask ZamPayroll AI</span>
        <kbd id="zpaiKbd">Ctrl K</kbd>
    </button>

    <div class="zpai-scrim" id="zpaiScrim"></div>

    <section class="zpai-panel" id="zpaiPanel" role="dialog" aria-modal="true" aria-labelledby="zpaiTitle" aria-hidden="true">
        <div class="zpai-flagline"></div>

        <header class="zpai-head">
            {!! $aiMark !!}
            <div>
                <h2 id="zpaiTitle">ZamPayroll AI @if($aiFull)<span class="zpai-mode" title="Full AI">AI</span>@endif</h2>
                <small>Ask a question or give me a task</small>
            </div>
            <span class="sp"></span>
            <button type="button" class="zpai-iconbtn" id="zpaiNew" title="New conversation" aria-label="New conversation"><i class="bi bi-pencil-square"></i></button>
            <button type="button" class="zpai-iconbtn" id="zpaiClose" title="Close (Esc)" aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </header>

        <div class="zpai-body" id="zpaiBody" aria-live="polite">
            <div class="zpai-hello" id="zpaiHello">
                <h3>Muli shani, {{ $aiFirst }}. What can I take off your plate?</h3>
                <p>I know your employees, leave, attendance and payroll. I'll always show you what I'm about to change before anything happens.</p>

                <div class="zpai-group">Ask</div>
                <div class="zpai-suggest">
                    <button type="button" data-ask="How many employees do we have?"><i class="bi bi-people"></i>How many employees do we have?</button>
                    <button type="button" data-ask="Who is on leave today?"><i class="bi bi-calendar2-heart"></i>Who is on leave today?</button>
                    <button type="button" data-ask="How much was our payroll last month, including PAYE and NAPSA?"><i class="bi bi-cash-stack"></i>How much was our payroll last month?</button>
                </div>
                <div class="zpai-group">Do</div>
                <div class="zpai-suggest">
                    <button type="button" class="do" data-ask="Show me pending leave requests so I can approve them."><i class="bi bi-check2-circle"></i>Review pending leave requests</button>
                    <button type="button" class="do" data-ask="Prepare this month's payroll."><i class="bi bi-play-circle"></i>Prepare this month's payroll</button>
                    <button type="button" class="do" data-attach><i class="bi bi-paperclip"></i>Add employees from a file or pasted list</button>
                </div>
            </div>
        </div>

        <footer class="zpai-foot">
            <form class="zpai-compose" id="zpaiForm" autocomplete="off">
                <textarea id="zpaiInput" rows="1" placeholder="Ask or tell me what to do…" aria-label="Message ZamPayroll AI"></textarea>
                <div class="zpai-compose-row">
                    <input type="file" id="zpaiFile" accept=".csv,.xlsx,.xls,.txt,.tsv" hidden>
                    <button type="button" class="zpai-iconbtn" id="zpaiAttach" title="Attach CSV or Excel" aria-label="Attach a file"><i class="bi bi-paperclip"></i></button>
                    <span class="zpai-chip" id="zpaiChip" hidden><i class="bi bi-file-earmark-spreadsheet"></i><span id="zpaiChipName"></span><button type="button" id="zpaiChipX" aria-label="Remove file"><i class="bi bi-x"></i></button></span>
                    <button type="submit" class="zpai-send" id="zpaiSend" aria-label="Send" disabled><i class="bi bi-arrow-up"></i></button>
                </div>
            </form>
            <div class="zpai-hint">Enter to send · Shift+Enter for a new line · Nothing changes until you press Confirm</div>
        </footer>

        <div class="zpai-drop" id="zpaiDrop" hidden><div><i class="bi bi-cloud-arrow-up" style="font-size:1.8rem"></i><br>Drop a CSV or Excel file of employees</div></div>
    </section>
</div>

<template id="zpaiMarkTpl">{!! $aiMark !!}</template>
<script src="{{ asset('assets/js/zp-ai.js') }}" defer></script>
@endif
