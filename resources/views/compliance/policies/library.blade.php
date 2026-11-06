@extends('layouts.app')
@section('title', 'Policy library · ZamPayroll')
@include('compliance.partials.head')
@section('content')
@php $LS = \App\Services\Compliance\TemplateLibrary::LEGAL_STATUS; @endphp
<div class="pr pp cc">
    <div class="pr-head"><div><h1>Policy library</h1><p>Policy templates written for Zambian workplaces. Pick one to create your company's version with your details and letterhead, then edit it to fit how you work.</p></div>
        <div class="pr-head-actions"><a class="pp-btn pp-btn-ghost" href="{{ route('compliance.policies.index') }}"><i class="bi bi-arrow-left"></i> Your policies</a></div></div>
    <div class="cc-note legal" style="margin-bottom:1rem">
        <b>How to read the labels.</b> <span class="cc-pill red">{{ $LS['required'] }}</span> means we found a specific provision requiring every employer to have it (the provision is quoted on the card).
        <span class="cc-pill amber">{{ $LS['conditional'] }}</span> applies only in some situations. <span class="cc-pill">{{ $LS['recommended'] }}</span> is good HR practice, not a legal requirement.
        Templates have not yet been reviewed by a qualified Zambian employment lawyer, and having a policy does not by itself make you compliant.
    </div>
    <div class="cc-tplgrid">
        @foreach($templates as $t)
            <div class="cc-tpl">
                <div style="display:flex;gap:.35rem;flex-wrap:wrap"><span class="cc-pill {{ $t->legal_status === 'required' ? 'red' : ($t->legal_status === 'conditional' ? 'amber' : '') }}">{{ $LS[$t->legal_status] ?? 'Policy' }}</span>
                    @unless($t->isMaster())<span class="cc-pill blue">Your template</span>@endunless
                    @if(in_array($t->id, $existing))<span class="cc-pill green">In use</span>@endif</div>
                <h3>{{ $t->name }}</h3>
                <p>{{ $t->description }}</p>
                @if($t->legal_note)<details><summary style="font-size:.82rem;font-weight:700;cursor:pointer">Legal basis</summary><p style="font-size:.8rem;margin-top:.3rem">{{ $t->legal_note }}</p></details>@endif
                <div class="actions">
                    <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('compliance.templates.show', $t) }}">Read</a>
                    <form method="POST" action="{{ route('compliance.policies.store') }}">@csrf<input type="hidden" name="template_id" value="{{ $t->id }}">
                        @if($letterheads->first())<input type="hidden" name="letterhead_id" value="{{ $letterheads->first()->id }}">@endif
                        <button class="pp-btn pp-btn-primary pp-btn-sm"><i class="bi bi-magic"></i> Create our policy</button></form>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
