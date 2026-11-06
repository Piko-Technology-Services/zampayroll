@extends('layouts.app')
@section('title', 'Edit ' . $contract->reference . ' · ZamPayroll')
@include('compliance.partials.head')
@section('content')
@php $L = \App\Services\Compliance\TemplateLibrary::PLACEHOLDERS; @endphp
<div class="pr pp cc">
    <div class="pr-head"><div><h1>{{ $contract->reference }} · {{ $contract->employee->full_name }}</h1>
        <p>{{ \App\Services\Compliance\TemplateLibrary::CONTRACT_TYPES[$contract->contract_type] ?? '' }} · version {{ $contract->current_version }}{{ $frozen ? ' (sent: saving creates version ' . ($contract->current_version + 1) . ')' : ' (draft)' }}</p></div>
        <div class="pr-head-actions">
            <a class="pp-btn pp-btn-ghost" href="{{ route('compliance.contracts.show', $contract) }}"><i class="bi bi-arrow-left"></i> Details</a>
            <a class="pp-btn pp-btn-ghost" href="{{ route('compliance.contracts.preview', $contract) }}" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Preview layout</a>
            <a class="pp-btn pp-btn-ghost" href="{{ route('compliance.contracts.docx', $contract) }}"><i class="bi bi-file-earmark-word"></i> Word</a>
        </div></div>

    @error('send')<div class="cc-alert danger"><i class="bi bi-exclamation-octagon lead"></i><div>{{ $message }}</div></div>@enderror
    @if($contract->decline_reason && $contract->status === 'declined')<div class="cc-alert warn"><i class="bi bi-chat-left-quote lead"></i><div><b>The employee declined:</b> {{ $contract->decline_reason }}</div></div>@endif

    <form method="POST" action="{{ route('compliance.contracts.update', $contract) }}" class="pr-layout">
        @csrf @method('PUT')
        <div>
            @include('compliance.partials.editor', ['html' => old('body', $version->body), 'placeholders' => $frozen ? [] : $L])
            <p class="cc-note" style="margin-top:.6rem">Words in <code>&#123;&#123;double braces&#125;&#125;</code> are filled in from ZamPayroll when the contract is sent. Use the panel on the right to fill gaps or override a detail for this contract only.</p>
        </div>
        <aside class="pr-side">
            <section class="pr-card">
                <div class="pr-card-head" style="margin-bottom:.5rem"><h2 style="font-size:1.05rem">Details</h2>
                    @if($missing)<span class="cc-pill amber">{{ count($missing) }} missing</span>@else<span class="cc-pill green">Complete</span>@endif</div>
                @if($frozen)
                    <p class="cc-note">This version was already sent, so its details are part of the text. Edit the text directly.</p>
                @else
                <div class="cc-fieldlist">
                    @foreach($used as $k)
                        @php $auto = $values[$k] ?? null; $isMissing = in_array($k, $missing, true); $long = in_array($k, ['job_responsibilities', 'allowances', 'working_hours', 'employee_address'], true); @endphp
                        <div class="pp-field {{ $isMissing ? 'need' : '' }}">
                            <label>{{ $L[$k] ?? $k }} @if($isMissing)<small>· needed</small>@elseif(!isset($data[$k]) || $data[$k] === null)<small>· from ZamPayroll</small>@else<small>· set for this contract</small>@endif</label>
                            @if($long)
                                <textarea class="pp-input" rows="{{ $k === 'job_responsibilities' ? 4 : 2 }}" name="data[{{ $k }}]" placeholder="{{ $auto }}">{{ old("data.$k", $data[$k] ?? '') }}</textarea>
                            @else
                                <input class="pp-input" name="data[{{ $k }}]" value="{{ old("data.$k", $data[$k] ?? '') }}" placeholder="{{ $auto ?? 'Type it here' }}">
                            @endif
                        </div>
                    @endforeach
                </div>
                <small class="pp-muted" style="display:block;margin-top:.5rem">Leave a box empty to use the value shown in grey from ZamPayroll.</small>
                @endif
            </section>
            <section class="pr-card" style="display:grid;gap:.6rem">
                <div class="pp-grid" style="grid-template-columns:1fr 1fr;gap:.5rem">
                    <div class="pp-field"><label>Start date</label><input type="date" class="pp-input" name="start_date" value="{{ old('start_date', optional($contract->start_date)->format('Y-m-d')) }}"></div>
                    <div class="pp-field"><label>End date</label><input type="date" class="pp-input" name="end_date" value="{{ old('end_date', optional($contract->end_date)->format('Y-m-d')) }}"></div>
                </div>
                <div class="pp-field"><label>Letterhead</label><select class="pp-select" name="letterhead_id">@foreach($letterheads as $lh)<option value="{{ $lh->id }}" @selected($contract->letterhead_id === $lh->id)>{{ $lh->name }}</option>@endforeach @if($letterheads->isEmpty())<option value="">From company profile</option>@endif</select></div>
                @if($frozen)<div class="pp-field"><label>What changed?</label><input class="pp-input" name="change_note" maxlength="255" placeholder="e.g. Corrected the job title"></div>@endif
                <button class="pp-btn pp-btn-ghost" name="then" value="save"><i class="bi bi-save"></i> Save draft</button>
                <button class="pp-btn pp-btn-primary" name="then" value="send" onclick="return confirm('Send this contract to {{ e($contract->employee->full_name) }} to review and sign?')"><i class="bi bi-send"></i> Save and send to employee</button>
                <small class="pp-muted">The employee signs in to their portal to read and sign. Then it goes through your approval workflow.</small>
            </section>
        </aside>
    </form>
</div>
@endsection
