@extends('layouts.app')
@section('title', $template->name . ' · ZamPayroll')
@include('compliance.partials.head')
@section('content')
<div class="pr pp cc">
    <div class="pr-head"><div><h1>{{ $template->name }}</h1><p>{{ $template->description }}</p></div>
        <div class="pr-head-actions">
            <a class="pp-btn pp-btn-ghost" href="{{ route('compliance.templates.index', ['kind' => $template->kind]) }}"><i class="bi bi-arrow-left"></i> Templates</a>
            <a class="pp-btn pp-btn-ghost" href="{{ route('compliance.templates.preview', $template) }}" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Preview on letterhead</a>
            @if($template->isMaster())
                <form method="POST" action="{{ route('compliance.templates.duplicate', $template) }}">@csrf<button class="pp-btn pp-btn-primary"><i class="bi bi-copy"></i> Make my copy</button></form>
            @else
                <a class="pp-btn pp-btn-primary" href="{{ route('compliance.templates.edit', $template) }}"><i class="bi bi-pencil"></i> Edit</a>
                <form method="POST" action="{{ route('compliance.templates.toggle', $template) }}">@csrf<button class="pp-btn pp-btn-ghost">{{ $template->is_active ? 'Deactivate' : 'Activate' }}</button></form>
            @endif
        </div></div>
    <div class="pr-layout">
        <div class="cc-paper scroll">{!! $body !!}</div>
        <aside class="pr-side">
            <section class="pr-card">
                <h2 style="font-size:1.05rem">About this template</h2>
                <div style="display:flex;gap:.35rem;flex-wrap:wrap;margin:.5rem 0">
                    <span class="cc-pill {{ $template->isMaster() ? 'dark' : 'blue' }}">{{ $template->isMaster() ? 'ZamPayroll master' : 'Your template' }}</span>
                    @if($template->legal_status)<span class="cc-pill {{ $template->legal_status === 'required' ? 'red' : ($template->legal_status === 'conditional' ? 'amber' : '') }}">{{ \App\Services\Compliance\TemplateLibrary::LEGAL_STATUS[$template->legal_status] }}</span>@endif
                    <span class="cc-pill">v{{ $template->version }}</span>
                </div>
                @if($template->legal_note)<p class="cc-note legal">{{ $template->legal_note }}</p>@endif
                <p class="cc-note">{{ $template->review_status === 'reviewed' ? 'Reviewed by a qualified employment lawyer on ' . optional($template->reviewed_on)->format('j M Y') . '.' : 'This template has not yet been reviewed by a qualified Zambian employment lawyer. Check it before relying on it.' }}</p>
                @if($template->masterUpdateAvailable())<p class="cc-note legal">ZamPayroll has updated the master (now v{{ $template->source->version }}; your copy came from v{{ $template->source_version }}). Your copy has not been changed. <a href="{{ route('compliance.templates.show', $template->source) }}">See the new master</a>.</p>@endif
                <h3 style="font-size:.9rem;margin-top:.8rem">Details filled in automatically</h3>
                <div style="display:flex;flex-wrap:wrap;gap:.3rem">@foreach($placeholders as $p)<span class="cc-pill">{{ \App\Services\Compliance\Placeholders::label($p) }}</span>@endforeach</div>
            </section>
            @if($versions->isNotEmpty())
            <section class="pr-card"><h2 style="font-size:1.05rem">Versions</h2>
                <ul class="cc-timeline" style="margin-top:.5rem">@foreach($versions as $v)<li><i class="bi bi-clock-history"></i><div>v{{ $v->version }}: {{ $v->change_note }}<small>{{ \Illuminate\Support\Carbon::parse($v->created_at)->format('j M Y H:i') }}</small></div></li>@endforeach</ul>
            </section>
            @endif
        </aside>
    </div>
</div>
@endsection
