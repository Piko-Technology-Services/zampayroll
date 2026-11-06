@extends('layouts.app')
@section('title', 'Templates · ZamPayroll')
@include('compliance.partials.head')
@section('content')
<div class="pr pp cc">
    <div class="pr-head"><div><h1>{{ $kind === 'contract' ? 'Contract templates' : 'Policy templates' }}</h1>
        <p>ZamPayroll templates are a starting point for Zambian employers. Make your own copy to change the wording. Your copies only affect your company.</p></div>
        <div class="pr-head-actions"><a class="pp-btn pp-btn-primary" href="{{ route('compliance.templates.create', ['kind' => $kind]) }}"><i class="bi bi-plus-lg"></i> New template</a></div></div>
    @if(count($kinds) > 1)
        <div class="cc-chips"><a href="{{ route('compliance.templates.index', ['kind' => 'contract']) }}" class="{{ $kind === 'contract' ? 'on' : '' }}">Contracts</a><a href="{{ route('compliance.templates.index', ['kind' => 'policy']) }}" class="{{ $kind === 'policy' ? 'on' : '' }}">Policies</a></div>
    @endif
    <p class="cc-note legal"><i class="bi bi-shield-exclamation"></i> {{ \App\Models\Compliance\ComplianceSetting::current()->disclaimer }}</p>
    <div class="cc-tplgrid" style="margin-top:1rem">
        @foreach($templates as $t)
            <div class="cc-tpl">
                <div style="display:flex;gap:.35rem;flex-wrap:wrap">
                    <span class="cc-pill {{ $t->isMaster() ? 'dark' : 'blue' }}">{{ $t->isMaster() ? 'ZamPayroll master' : 'Your template' }}</span>
                    @if($t->legal_status)<span class="cc-pill {{ $t->legal_status === 'required' ? 'red' : ($t->legal_status === 'conditional' ? 'amber' : '') }}">{{ \App\Services\Compliance\TemplateLibrary::LEGAL_STATUS[$t->legal_status] }}</span>@endif
                    @unless($t->is_active)<span class="cc-pill">Inactive</span>@endunless
                    @if($t->masterUpdateAvailable())<span class="cc-pill amber">Newer master available</span>@endif
                </div>
                <h3>{{ $t->name }}</h3>
                <p>{{ $t->description }}</p>
                <small class="pp-muted">Version {{ $t->version }} · {{ $t->review_status === 'reviewed' ? 'Legally reviewed ' . optional($t->reviewed_on)->format('M Y') : 'Not yet legally reviewed' }}</small>
                <div class="actions">
                    <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('compliance.templates.show', $t) }}">View</a>
                    @if($t->isMaster())
                        <form method="POST" action="{{ route('compliance.templates.duplicate', $t) }}">@csrf<button class="pp-btn pp-btn-ghost pp-btn-sm"><i class="bi bi-copy"></i> Make my copy</button></form>
                    @else
                        <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('compliance.templates.edit', $t) }}"><i class="bi bi-pencil"></i> Edit</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
