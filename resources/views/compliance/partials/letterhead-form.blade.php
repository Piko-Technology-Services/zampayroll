@php
    $s = $letterhead?->allSettings() ?? \App\Models\Compliance\Letterhead::DEFAULTS;
    $mode = old('mode', $letterhead->mode ?? 'generated');
@endphp
<div style="display:grid;gap:.8rem">
    @if($letterhead?->id)<input type="hidden" name="letterhead_id" value="{{ $letterhead->id }}">@endif
    <div class="pp-field"><label for="lh_name">Name</label><input class="pp-input" id="lh_name" name="name" required maxlength="120" value="{{ old('name', $letterhead->name ?? 'Company letterhead') }}"></div>
    <fieldset class="pp-field" style="border:0;padding:0;margin:0"><legend style="font-size:.85rem;font-weight:700;margin-bottom:.4rem">Letterhead</legend>
        <label class="pp-toggle"><input type="radio" name="mode" value="generated" @checked($mode === 'generated') data-lhmode> Make one from our company profile</label>
        <label class="pp-toggle"><input type="radio" name="mode" value="custom" @checked($mode === 'custom') data-lhmode> Use our own letterhead (upload)</label>
    </fieldset>
    <div data-lhcustom @if($mode !== 'custom') hidden @endif style="display:grid;gap:.6rem">
        <div class="pp-field"><label>Header <small>PNG or JPG, full page width (a PDF works if the server can convert it)</small></label><input type="file" class="pp-input" name="header_file" accept=".png,.jpg,.jpeg,.pdf">
            @if($letterhead?->header_path)<small class="pp-muted">A header is saved. Upload a new file to replace it.</small>@endif</div>
        <div class="pp-field"><label>Footer <small>optional</small></label><input type="file" class="pp-input" name="footer_file" accept=".png,.jpg,.jpeg,.pdf">
            @if($letterhead?->footer_path)<label class="pp-toggle"><input type="checkbox" name="remove_footer" value="1"> Remove the saved footer</label>@endif</div>
    </div>
    <div class="pp-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:.6rem">
        <div class="pp-field"><label>Header height (mm)</label><input class="pp-input" type="number" step="1" min="10" max="90" name="header_height" value="{{ old('header_height', $s['header_height']) }}"></div>
        <div class="pp-field"><label>Footer height (mm)</label><input class="pp-input" type="number" step="1" min="5" max="60" name="footer_height" value="{{ old('footer_height', $s['footer_height']) }}"></div>
        <div class="pp-field"><label>Space below header (mm)</label><input class="pp-input" type="number" step="1" min="0" max="60" name="margin_top" value="{{ old('margin_top', $s['margin_top']) }}"></div>
        <div class="pp-field"><label>Space above footer (mm)</label><input class="pp-input" type="number" step="1" min="0" max="60" name="margin_bottom" value="{{ old('margin_bottom', $s['margin_bottom']) }}"></div>
        <div class="pp-field"><label>Left margin (mm)</label><input class="pp-input" type="number" step="1" min="5" max="50" name="margin_left" value="{{ old('margin_left', $s['margin_left']) }}"></div>
        <div class="pp-field"><label>Right margin (mm)</label><input class="pp-input" type="number" step="1" min="5" max="50" name="margin_right" value="{{ old('margin_right', $s['margin_right']) }}"></div>
        <div class="pp-field"><label>Logo position</label><select class="pp-select" name="logo_position">@foreach(['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'] as $k => $l)<option value="{{ $k }}" @selected(old('logo_position', $s['logo_position']) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="pp-field"><label>Accent colour</label><input class="pp-input" type="color" name="accent" value="{{ old('accent', $s['accent']) }}" style="height:42px;padding:.2rem"></div>
        <div class="pp-field"><label>Font</label><select class="pp-select" name="font">@foreach(\App\Models\Compliance\Letterhead::FONTS as $k => $l)<option value="{{ $k }}" @selected(old('font', $s['font']) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="pp-field"><label>Text size (pt)</label><input class="pp-input" type="number" step=".5" min="8" max="14" name="font_size" value="{{ old('font_size', $s['font_size']) }}"></div>
    </div>
    <input type="hidden" name="page_numbers" value="0"><label class="pp-toggle"><input type="checkbox" name="page_numbers" value="1" @checked(old('page_numbers', $s['page_numbers']))> Page numbers ("Page 1 of 3")</label>
    <input type="hidden" name="show_registration" value="0"><label class="pp-toggle"><input type="checkbox" name="show_registration" value="1" @checked(old('show_registration', $s['show_registration']))> Show TPIN, PACRA and NAPSA numbers in the footer</label>
    <input type="hidden" name="is_default" value="0"><label class="pp-toggle"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $letterhead->is_default ?? false))> Use as the default letterhead</label>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <button class="pp-btn pp-btn-primary"><i class="bi bi-check2"></i> Save letterhead</button>
        <button class="pp-btn pp-btn-ghost" formaction="{{ route('compliance.letterheads.preview') }}" formtarget="_blank" formnovalidate><i class="bi bi-eye"></i> Preview as PDF</button>
    </div>
</div>
@push('scripts')
<script>
document.querySelectorAll('[data-lhmode]').forEach(r => r.addEventListener('change', () => {
  document.querySelectorAll('[data-lhcustom]').forEach(b => b.hidden = document.querySelector('[data-lhmode]:checked').value !== 'custom');
}));
</script>
@endpush
