@extends('layouts.app')
@section('title', 'Compliance settings · ZamPayroll')
@include('compliance.partials.head')
@section('content')
<div class="pr pp cc">
    <div class="pr-head"><div><h1>Compliance settings</h1><p>Your letterheads, approval workflows and the defaults used in new documents.</p></div></div>

    <div class="pr-layout">
        <div>
            <section class="pr-card" id="letterheads">
                <div class="pr-card-head"><div><h2>Letterheads &amp; branding</h2><div class="sub">Applied automatically to contracts, policies and HR letters. You can choose another when you create a document.</div></div></div>
                @forelse($letterheads as $lh)
                    <div class="cc-step" style="align-items:center;margin-bottom:.5rem">
                        <span class="n"><i class="bi {{ $lh->mode === 'custom' ? 'bi-image' : 'bi-building' }}"></i></span>
                        <div style="flex:1"><b>{{ $lh->name }} @if($lh->is_default)<span class="cc-pill green">Default</span>@endif</b>
                            <small>{{ $lh->mode === 'custom' ? 'Your uploaded letterhead' : 'Made from your company profile' }} · {{ $lh->setting('font') }} {{ $lh->setting('font_size') }}pt</small></div>
                        <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('compliance.letterheads.sample', $lh) }}" target="_blank"><i class="bi bi-eye"></i> Sample</a>
                        <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('compliance.letterheads.edit', $lh) }}"><i class="bi bi-pencil"></i> Edit</a>
                        @unless($lh->is_default)
                            <form method="POST" action="{{ route('compliance.letterheads.default', $lh) }}">@csrf<button class="pp-btn pp-btn-ghost pp-btn-sm">Make default</button></form>
                        @endunless
                    </div>
                @empty
                    <div class="cc-note">No letterhead yet. Documents use a simple header made from your company profile until you save one below.</div>
                @endforelse

                <details style="margin-top:1rem" @if($errors->has('header_file') || $letterheads->isEmpty()) open @endif>
                    <summary style="font-weight:700;cursor:pointer">Add a letterhead</summary>
                    <form method="POST" action="{{ route('compliance.letterheads.store') }}" enctype="multipart/form-data" style="margin-top:.8rem">
                        @csrf
                        <div class="cc-note" style="margin-bottom:.7rem">From your company profile we use: <b>{{ $company->name }}</b>{{ $company->logo_path ? ', your logo' : '' }}{{ $company->address ? ', ' . $company->address : '' }}{{ $company->phone ? ', ' . $company->phone : '' }}{{ $company->email ? ', ' . $company->email : '' }}. Update these in <a href="{{ route('company.settings') }}">Company settings</a>.</div>
                        @include('compliance.partials.letterhead-form', ['letterhead' => null])
                    </form>
                </details>
            </section>

            <section class="pr-card" id="workflows">
                <div class="pr-card-head"><div><h2>Approval workflows</h2><div class="sub">Who reviews, approves and signs, in order. Steps go to a person or to anyone with a role. Nobody can act for someone else unless you name them as that step's delegate.</div></div>
                    <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                        <a class="pp-btn pp-btn-primary pp-btn-sm" href="{{ route('compliance.workflows.create') }}"><i class="bi bi-plus-lg"></i> Contract workflow</a>
                        <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('compliance.workflows.create', ['for' => 'policy']) }}"><i class="bi bi-plus-lg"></i> Policy workflow</a>
                    </div>
                </div>
                @forelse($workflows as $wf)
                    <div style="border:1px solid var(--zp-line);border-radius:14px;padding:.8rem;margin-bottom:.6rem">
                        <div style="display:flex;justify-content:space-between;gap:.5rem;align-items:center;flex-wrap:wrap">
                            <b>{{ $wf->name }}</b>
                            <div style="display:flex;gap:.35rem;align-items:center">
                                <span class="cc-pill {{ $wf->applies_to === 'contract' ? 'blue' : 'amber' }}">{{ $wf->applies_to === 'contract' ? 'Contracts' : 'Policies' }}{{ $wf->applies_to === 'contract' && $wf->contract_types ? ': ' . collect($wf->contract_types)->map(fn ($t) => \App\Services\Compliance\TemplateLibrary::CONTRACT_TYPES[$t] ?? $t)->implode(', ') : '' }}</span>
                                @unless($wf->is_active)<span class="cc-pill">Off</span>@endunless
                                <a class="pp-btn pp-btn-ghost pp-btn-sm" href="{{ route('compliance.workflows.edit', $wf) }}">Edit</a>
                            </div>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.55rem;align-items:center">
                            <span class="cc-pill">{{ $wf->applies_to === 'contract' ? 'Employee signs' : 'HR prepares' }}</span>
                            @foreach($wf->steps as $st)
                                <i class="bi bi-arrow-right pp-muted"></i>
                                <span class="cc-pill {{ $st->action === 'sign' ? 'dark' : '' }}">{{ $st->label }} · {{ $st->approver_type === 'user' ? ($st->approver?->name ?? 'removed user') : 'role: ' . $st->approver_role_key }}{{ $st->delegate ? ' (delegate: ' . $st->delegate->name . ')' : '' }}</span>
                            @endforeach
                            <i class="bi bi-arrow-right pp-muted"></i><span class="cc-pill green">{{ $wf->applies_to === 'contract' ? 'Fully executed' : 'Policy active' }}</span>
                        </div>
                    </div>
                @empty
                    <div class="cc-note">No workflows yet. Contracts need one with a final company signatory before they can be sent. Without a policy workflow, policies are published by HR directly.</div>
                @endforelse
            </section>
        </div>

        <aside class="pr-side">
            <section class="pr-card">
                <div class="pr-card-head" style="margin-bottom:.6rem"><h2 style="font-size:1.05rem">Document defaults</h2></div>
                <form method="POST" action="{{ route('compliance.settings.update') }}" style="display:grid;gap:.6rem">
                    @csrf @method('PUT')
                    <div class="pp-field"><label>Working hours</label><input class="pp-input" name="working_hours" value="{{ old('working_hours', $settings->working_hours) }}" maxlength="255"></div>
                    <div class="pp-field"><label>Notice period</label><input class="pp-input" name="notice_period" value="{{ old('notice_period', $settings->notice_period) }}" maxlength="120"></div>
                    <div class="pp-field"><label>Probation (months) <small>max 3 by law</small></label><input class="pp-input" type="number" min="1" max="3" name="probation_months" value="{{ old('probation_months', $settings->probation_months) }}"></div>
                    <div class="pp-field"><label>Allowances wording</label><input class="pp-input" name="allowances" value="{{ old('allowances', $settings->allowances) }}" maxlength="255"></div>
                    <div class="pp-field"><label>Default signatory name</label><input class="pp-input" name="signatory_name" value="{{ old('signatory_name', $settings->signatory_name) }}" maxlength="120"></div>
                    <div class="pp-field"><label>Default signatory title</label><input class="pp-input" name="signatory_title" value="{{ old('signatory_title', $settings->signatory_title) }}" maxlength="120"></div>
                    <div class="pp-field"><label>Reminder days before expiry</label><input class="pp-input" name="reminder_days" value="{{ old('reminder_days', implode(', ', $settings->reminderDays())) }}" placeholder="90, 30, 7, 0"></div>
                    <div class="pp-field"><label>Keep records for (years) <small>min 5</small></label><input class="pp-input" type="number" min="5" max="100" name="retention_years" value="{{ old('retention_years', $settings->retention_years) }}"></div>
                    <div class="pp-field"><label>Disclaimer shown with templates</label><textarea class="pp-input" rows="5" name="disclaimer" maxlength="2000">{{ old('disclaimer', $settings->disclaimer) }}</textarea></div>
                    <button class="pp-btn pp-btn-primary"><i class="bi bi-check2"></i> Save defaults</button>
                </form>
                <p class="cc-note" style="margin-top:.7rem">The Employment Code Act requires a written contract to be kept for five years after it ends (s.23(3)). Executed contracts are never deleted when an employee leaves.</p>
            </section>
        </aside>
    </div>
</div>
@endsection
