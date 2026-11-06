@extends('layouts.app')
@section('title', ($template->exists ? 'Edit ' . $template->name : 'New template') . ' · ZamPayroll')
@include('compliance.partials.head')
@section('content')
<div class="pr pp cc">
    <div class="pr-head"><div><h1>{{ $template->exists ? 'Edit template' : 'New ' . $kind . ' template' }}</h1>
        <p>Use "Insert a detail" to add information ZamPayroll fills in for each employee, such as their name, NRC or salary.</p></div>
        <div class="pr-head-actions"><a class="pp-btn pp-btn-ghost" href="{{ $template->exists ? route('compliance.templates.show', $template) : route('compliance.templates.index', ['kind' => $kind]) }}"><i class="bi bi-arrow-left"></i> Back</a></div></div>
    <form method="POST" action="{{ $template->exists ? route('compliance.templates.update', $template) : route('compliance.templates.store') }}" enctype="multipart/form-data" class="pr-layout">
        @csrf @if($template->exists) @method('PUT') @endif
        <input type="hidden" name="kind" value="{{ $kind }}">
        <div>
            @include('compliance.partials.editor', ['html' => old('body', $template->body ?? ''), 'placeholders' => \App\Services\Compliance\TemplateLibrary::PLACEHOLDERS])
        </div>
        <aside class="pr-side">
            <section class="pr-card" style="display:grid;gap:.6rem">
                <div class="pp-field"><label>Name</label><input class="pp-input" name="name" required maxlength="160" value="{{ old('name', $template->name) }}"></div>
                @if($kind === 'contract')
                    <div class="pp-field"><label>Contract type</label><select class="pp-select" name="key">@foreach(\App\Services\Compliance\TemplateLibrary::CONTRACT_TYPES as $k => $l)<option value="{{ $k }}" @selected(old('key', $template->key) === $k)>{{ $l }}</option>@endforeach</select></div>
                @else
                    <input type="hidden" name="key" value="{{ old('key', $template->key ?: 'custom') }}">
                    <div class="pp-field"><label>Legal status</label><select class="pp-select" name="legal_status">@foreach(\App\Services\Compliance\TemplateLibrary::LEGAL_STATUS as $k => $l)<option value="{{ $k }}" @selected(old('legal_status', $template->legal_status ?? 'recommended') === $k)>{{ $l }}</option>@endforeach</select>
                        <small class="pp-muted">Only mark a policy as required by law if you have checked the legislation.</small></div>
                @endif
                <div class="pp-field"><label>Description</label><textarea class="pp-input" rows="3" name="description" maxlength="1000">{{ old('description', $template->description) }}</textarea></div>
                @if($template->exists)
                    <div class="pp-field"><label>What changed?</label><input class="pp-input" name="change_note" maxlength="255" placeholder="e.g. Added overtime clause"></div>
                @else
                    <div class="pp-field"><label>Or start from a Word file <small>.docx</small></label><input class="pp-input" type="file" name="word_file" accept=".docx">
                        <small class="pp-muted">We import the text, headings, lists and tables. Fonts and images are not carried over.</small></div>
                @endif
                <button class="pp-btn pp-btn-primary"><i class="bi bi-check2"></i> Save template</button>
                <p class="cc-note">Saving creates a new version. Contracts and policies already made from this template are not changed.</p>
            </section>
        </aside>
    </form>
</div>
@endsection
