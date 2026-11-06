@extends('layouts.app')
@section('title', 'Edit letterhead · ZamPayroll')
@include('compliance.partials.head')
@section('content')
<div class="pr pp cc">
    <div class="pr-head"><div><h1>Edit letterhead</h1><p>Changes apply to documents you create or preview from now on. Executed contracts keep the letterhead they were signed with.</p></div>
        <div class="pr-head-actions"><a class="pp-btn pp-btn-ghost" href="{{ route('compliance.settings') }}"><i class="bi bi-arrow-left"></i> Back</a></div></div>
    <div class="pr-layout">
        <section class="pr-card">
            <form method="POST" action="{{ route('compliance.letterheads.update', $letterhead) }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                @include('compliance.partials.letterhead-form', ['letterhead' => $letterhead])
            </form>
            <form method="POST" action="{{ route('compliance.letterheads.destroy', $letterhead) }}" style="margin-top:1rem" onsubmit="return confirm('Remove this letterhead?')">@csrf @method('DELETE')
                <button class="pp-btn pp-btn-ghost pp-btn-sm" style="color:var(--zp-red)"><i class="bi bi-trash"></i> Remove letterhead</button></form>
        </section>
        <aside class="pr-side"><section class="pr-card"><h2 style="font-size:1.05rem">Saved version</h2>
            <iframe class="cc-preview-frame" style="height:420px" src="{{ route('compliance.letterheads.sample', $letterhead) }}" title="Letterhead sample"></iframe></section></aside>
    </div>
</div>
@endsection
