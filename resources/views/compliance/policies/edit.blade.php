@extends('layouts.app')
@section('title', 'Edit ' . $policy->title . ' · ZamPayroll')
@include('compliance.partials.head')
@section('content')
<div class="pr pp cc">
    <div class="pr-head"><div><h1>{{ $policy->title }}</h1><p>{{ $policy->reference }} · {{ $policy->active_version ? 'Revision v' . $policy->current_version . ' (v' . $policy->active_version . ' stays active until this is approved)' : 'Draft v' . $policy->current_version }}</p></div>
        <div class="pr-head-actions">
            <a class="pp-btn pp-btn-ghost" href="{{ route('compliance.policies.show', $policy) }}"><i class="bi bi-arrow-left"></i> Details</a>
            <a class="pp-btn pp-btn-ghost" href="{{ route('compliance.policies.preview', $policy) }}" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Preview layout</a>
            <a class="pp-btn pp-btn-ghost" href="{{ route('compliance.policies.docx', $policy) }}"><i class="bi bi-file-earmark-word"></i> Word</a>
        </div></div>
    @if($policy->status === 'changes')@php $back = $policy->approvals()->whereIn('status', ['rejected', 'changes'])->latest('id')->first(); @endphp
        <div class="cc-alert warn"><i class="bi bi-arrow-return-left lead"></i><div><b>{{ $back?->acted_by_name }} ({{ $back?->label }}):</b> {{ $back?->comment }}</div></div>@endif
    <form method="POST" action="{{ route('compliance.policies.update', $policy) }}" class="pr-layout">
        @csrf @method('PUT')
        <div>
            @include('compliance.partials.editor', ['html' => old('body', $version->body), 'placeholders' => array_intersect_key(\App\Services\Compliance\TemplateLibrary::PLACEHOLDERS, array_flip(['company_name', 'company_address', 'company_phone', 'company_email', 'policy_owner', 'effective_date', 'today']))])
            <p class="cc-note" style="margin-top:.6rem">Company details in <code>&#123;&#123;double braces&#125;&#125;</code> are filled in when you submit. Add your own clauses anywhere.</p>
        </div>
        <aside class="pr-side">
            <section class="pr-card" style="display:grid;gap:.6rem">
                <div class="pp-field"><label>Title</label><input class="pp-input" name="title" required maxlength="160" value="{{ old('title', $policy->title) }}"></div>
                <div class="pp-field"><label>Next review date</label><input class="pp-input" type="date" name="review_date" value="{{ old('review_date', optional($policy->review_date)->format('Y-m-d')) }}"></div>
                <div class="pp-field"><label>Letterhead</label><select class="pp-select" name="letterhead_id">@foreach($letterheads as $lh)<option value="{{ $lh->id }}" @selected($policy->letterhead_id === $lh->id)>{{ $lh->name }}</option>@endforeach @if($letterheads->isEmpty())<option value="">From company profile</option>@endif</select></div>
                <input type="hidden" name="requires_acknowledgement" value="0"><label class="pp-toggle"><input type="checkbox" name="requires_acknowledgement" value="1" @checked(old('requires_acknowledgement', $policy->requires_acknowledgement))> Employees must acknowledge it</label>
                @if($policy->active_version)
                    <div class="pp-field"><label>Reason for this update <small>required</small></label><input class="pp-input" name="change_reason" maxlength="255" value="{{ old('change_reason', $version->change_reason) }}">@error('change_reason')<small style="color:var(--zp-red)">{{ $message }}</small>@enderror</div>
                    <input type="hidden" name="fresh_ack" value="0"><label class="pp-toggle"><input type="checkbox" name="fresh_ack" value="1" checked> Ask everyone to acknowledge the new version</label>
                @endif
                <button class="pp-btn pp-btn-ghost" name="then" value="save"><i class="bi bi-save"></i> Save draft</button>
                <button class="pp-btn pp-btn-primary" name="then" value="submit"><i class="bi bi-send-check"></i> {{ $hasWorkflow ? 'Save and submit for approval' : 'Save and publish' }}</button>
                <small class="pp-muted">{{ $hasWorkflow ? 'When every approver agrees, the policy becomes active and employees are asked to acknowledge it.' : 'You have no policy workflow, so it is published straight away.' }}</small>
            </section>
            <p class="cc-note legal">{{ \App\Models\Compliance\ComplianceSetting::current()->disclaimer }}</p>
        </aside>
    </form>
</div>
@endsection
