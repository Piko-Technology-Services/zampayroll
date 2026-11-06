@php $d = $doc ?? null; @endphp
<div class="pp-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.6rem">
    @if(!empty($withTitle))<div class="pp-field" style="grid-column:1/-1"><label>Title {{ $titleHint ?? '' }}</label><input class="pp-input" name="title" maxlength="200" value="{{ old('title', $d?->title) }}" @if($d) required @endif></div>@endif
    <div class="pp-field"><label>Category</label><select class="pp-select" name="category_id"><option value="">Uncategorised</option>
        @foreach($categories as $c)<optgroup label="{{ $c->name }}"><option value="{{ $c->id }}" @selected(old('category_id', $d?->category_id) == $c->id)>{{ $c->name }} (general)</option>@foreach($c->children as $ch)<option value="{{ $ch->id }}" @selected(old('category_id', $d?->category_id) == $ch->id)>{{ $ch->name }}</option>@endforeach</optgroup>@endforeach</select></div>
    <div class="pp-field"><label>Reference number</label><input class="pp-input" name="reference" maxlength="80" value="{{ old('reference', $d?->reference) }}"></div>
    <div class="pp-field"><label>Issue date</label><input type="date" class="pp-input" name="issue_date" value="{{ old('issue_date', optional($d?->issue_date)->format('Y-m-d')) }}"></div>
    <div class="pp-field"><label>Effective date</label><input type="date" class="pp-input" name="effective_date" value="{{ old('effective_date', optional($d?->effective_date)->format('Y-m-d')) }}"></div>
    <div class="pp-field"><label>Expiry date <small>optional</small></label><input type="date" class="pp-input" name="expiry_date" value="{{ old('expiry_date', optional($d?->expiry_date)->format('Y-m-d')) }}"></div>
    <div class="pp-field"><label>Review date <small>optional</small></label><input type="date" class="pp-input" name="review_date" value="{{ old('review_date', optional($d?->review_date)->format('Y-m-d')) }}"></div>
    <div class="pp-field"><label>Responsible department</label><input class="pp-input" name="department" list="ccDepts" maxlength="120" value="{{ old('department', $d?->department) }}"></div>
    <div class="pp-field"><label>Branch</label><input class="pp-input" name="branch" maxlength="120" value="{{ old('branch', $d?->branch) }}"></div>
    <div class="pp-field"><label>Employee <small>if it belongs to someone</small></label><select class="pp-select" name="employee_id"><option value="">Nobody in particular</option>@foreach($employees as $e)<option value="{{ $e->id }}" @selected(old('employee_id', $d?->employee_id) == $e->id)>{{ $e->first_name }} {{ $e->last_name }} ({{ $e->employee_id }})</option>@endforeach</select>@error('employee_id')<small style="color:var(--zp-red)">{{ $message }}</small>@enderror</div>
    <div class="pp-field"><label>Compliance requirement <small>optional</small></label><input class="pp-input" name="compliance_requirement" maxlength="160" value="{{ old('compliance_requirement', $d?->compliance_requirement) }}" placeholder="e.g. OHS Act 2025 s.14"></div>
    <div class="pp-field" style="grid-column:1/-1"><label>Tags <small>comma separated</small></label><input class="pp-input" name="tags" maxlength="300" value="{{ old('tags', implode(', ', $d?->tags ?? [])) }}" placeholder="labour inspection, 2026"></div>
    <div class="pp-field" style="grid-column:1/-1"><label>Description</label><textarea class="pp-input" name="description" rows="2" maxlength="2000">{{ old('description', $d?->description) }}</textarea></div>
</div>
<datalist id="ccDepts">@foreach($departments as $dep)<option value="{{ $dep }}">@endforeach</datalist>
<fieldset style="border:1px solid var(--zp-line);border-radius:14px;padding:.8rem;margin-top:.8rem">
    <legend style="font-size:.9rem;font-weight:800;padding:0 .3rem;width:auto">Who can see it</legend>
    <div class="pp-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.6rem">
        <div class="pp-field"><label>Access</label><select class="pp-select" name="access_level" data-access>@foreach(\App\Models\Compliance\CompanyDocument::ACCESS as $k => $l)<option value="{{ $k }}" @selected(old('access_level', $d?->access_level ?? 'hr') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="pp-field" data-access-dept><label>Department</label><input class="pp-input" name="access_department" list="ccDepts" maxlength="120" value="{{ old('access_department', $d?->access_department) }}">@error('access_department')<small style="color:var(--zp-red)">{{ $message }}</small>@enderror</div>
    </div>
    <input type="hidden" name="share_with_employees" value="0">
    <label class="pp-toggle" style="margin-top:.5rem"><input type="checkbox" name="share_with_employees" value="1" @checked(old('share_with_employees', $d?->share_with_employees))> Also show it in the employee portal (company-wide: everyone; one employee: only them; one department: that department)</label>
    <div class="pp-field" style="margin-top:.5rem"><label>Notes <small>visible to staff only</small></label><textarea class="pp-input" name="notes" rows="2" maxlength="2000">{{ old('notes', $d?->notes) }}</textarea></div>
</fieldset>
@push('scripts')
<script>
(() => { const a = document.querySelector('[data-access]'), d = document.querySelector('[data-access-dept]'); if (!a) return;
  const t = () => d.hidden = a.value !== 'department'; a.addEventListener('change', t); t(); })();
</script>
@endpush
