@extends('layouts.app')

@section('content')

<div class="bpt p-5">

    {{-- ===== HEADER ===== --}}
    <div class="bpt-header">
        <div>
            <a href="{{ route('company.settings') }}" class="bpt-back"><i class="bi bi-arrow-left"></i> Company Settings</a>
            <div class="bpt-eyebrow">Payroll</div>
            <h1 class="bpt-title">Bank Payment Templates</h1>
            <div class="bpt-subtitle">Choose the CSV layout your bank's bulk-upload portal expects. Every "Bank Bulk Payments" export uses your default template.</div>
        </div>
    </div>

    @if(session('success'))
        <div class="bpt-alert"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
    @endif

    {{-- ===== EXISTING TEMPLATES ===== --}}
    <div class="bpt-card">
        <div class="bpt-section-label"><span class="bpt-section-no">01</span>Your Templates</div>

        @forelse($templates as $template)
            <div class="bpt-template-row">
                <div class="bpt-template-info">
                    <div class="bpt-template-name">
                        {{ $template->name }}
                        @if($template->is_default)
                            <span class="bpt-badge-default">Default</span>
                        @endif
                        <span class="bpt-badge-source">{{ $template->source === 'custom' ? 'Custom upload' : 'Preset' }}</span>
                    </div>
                    <div class="bpt-template-meta">
                        {{ $template->bank?->name ?? 'No specific bank linked' }} · {{ count($template->columns) }} columns
                        · delimiter "{{ $template->delimiter }}"
                    </div>
                </div>
                <div class="bpt-template-actions">
                    @if(!$template->is_default)
                        <form method="POST" action="{{ route('bank-templates.default', $template) }}">
                            @csrf
                            <button class="bpt-btn bpt-btn-outline">Make Default</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('bank-templates.destroy', $template) }}" onsubmit="return confirm('Delete this template?');">
                        @csrf @method('DELETE')
                        <button class="bpt-btn bpt-btn-warn">Delete</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="bpt-empty">No templates yet — set one up below.</div>
        @endforelse
    </div>

    {{-- ===== OPTION A: PRESET BANK ===== --}}
    <div class="bpt-card">
        <div class="bpt-section-label"><span class="bpt-section-no">02</span>Start From a Bank</div>
        <div class="bpt-field-hint" style="margin:-.5rem 0 1rem;">
            Pick your company's disbursing bank. We'll start you off with a universal column layout you can still adjust — headers, prefixes, and column order all editable below.
        </div>

        <div class="bpt-bank-grid">
            @foreach($banks as $bank)
                <label class="bpt-bank-chip">
                    <input type="radio" name="preset_bank_id" value="{{ $bank->id }}" onchange="bptSelectPresetBank({{ $bank->id }}, '{{ $bank->name }}')">
                    <span>{{ $bank->short_name ?? $bank->name }}</span>
                </label>
            @endforeach
            <label class="bpt-bank-chip">
                <input type="radio" name="preset_bank_id" value="" onchange="bptSelectPresetBank(null, 'Universal')" checked>
                <span>Not listed / Universal</span>
            </label>
        </div>

        <form method="POST" action="{{ route('bank-templates.preset') }}" id="bptPresetForm">
            @csrf
            <input type="hidden" name="bank_id" id="bptPresetBankId" value="">

            <div class="bpt-field-row">
                <label class="bpt-field-label">Template Name</label>
                <input type="text" name="name" id="bptPresetName" class="bpt-input" required value="Universal Bank Template">
            </div>

            <div id="bptPresetColumns"></div>

            <div class="bpt-actions">
                <button type="button" class="bpt-btn bpt-btn-outline" onclick="bptAddColumn('bptPresetColumns', 'preset')"><i class="bi bi-plus-lg"></i> Add Column</button>
                <button class="bpt-btn bpt-btn-primary"><i class="bi bi-save"></i> Save Template</button>
            </div>
        </form>
    </div>

    {{-- ===== OPTION B: UPLOAD CUSTOM CSV ===== --}}
    <div class="bpt-card">
        <div class="bpt-section-label"><span class="bpt-section-no">03</span>Or Upload Your Bank's Template</div>
        <div class="bpt-field-hint" style="margin:-.5rem 0 1rem;">
            Upload a sample CSV from your bank (just the header row matters). We'll guess what each column means — review and confirm the mapping before saving.
        </div>

        <div class="bpt-upload-row">
            <input type="file" id="bptCustomFile" accept=".csv,.txt" class="bpt-input">
            <button type="button" class="bpt-btn bpt-btn-outline" onclick="bptParseUpload()"><i class="bi bi-upload"></i> Read Header Row</button>
        </div>

        <form method="POST" action="{{ route('bank-templates.custom') }}" id="bptCustomForm" style="display:none; margin-top:1rem;">
            @csrf
            <div class="bpt-field-row">
                <label class="bpt-field-label">Bank (optional)</label>
                <select name="bank_id" class="bpt-input bpt-input-narrow">
                    <option value="">Not listed</option>
                    @foreach($banks as $bank)
                        <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="bpt-field-row">
                <label class="bpt-field-label">Template Name</label>
                <input type="text" name="name" id="bptCustomName" class="bpt-input" required value="Custom Bank Template">
            </div>

            <div id="bptCustomColumns"></div>

            <div class="bpt-actions">
                <button type="button" class="bpt-btn bpt-btn-outline" onclick="bptAddColumn('bptCustomColumns', 'custom')"><i class="bi bi-plus-lg"></i> Add Column</button>
                <button class="bpt-btn bpt-btn-primary"><i class="bi bi-save"></i> Save Mapped Template</button>
            </div>
        </form>
    </div>

</div>

{{-- ===== COLUMN ROW TEMPLATE (cloned via JS) ===== --}}
<template id="bptColumnRowTemplate">
    <div class="bpt-col-row">
        <input type="text" class="bpt-input bpt-col-header" placeholder="Column header">
        <select class="bpt-input bpt-col-field">
            @foreach($fields as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <input type="text" class="bpt-input bpt-input-narrow bpt-col-prefix" placeholder="Prefix (e.g. #)">
        <input type="text" class="bpt-input bpt-col-static" placeholder="Fixed value (if 'Fixed Text')" style="display:none;">
        <button type="button" class="bpt-col-remove" onclick="this.closest('.bpt-col-row').remove()"><i class="bi bi-x-lg"></i></button>
    </div>
</template>

{{-- ===== STYLES ===== --}}
<style>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap');

.bpt * { box-sizing: border-box; }
.bpt {
    --ink: #00742E; --ink-soft: #58687D; --ink-faint: #93A0B2; --brass: #00742E;
    --line: #E5E9F0; --active-bg: #EAF3EC; --warn: #D50115; --warn-bg: #F7E7E2;
    font-family: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--ink); background: #fff; padding: 1.5rem 0 2.5rem; max-width: 900px; margin: 0 auto;
}
.bpt-back { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 600; color: var(--ink-soft); text-decoration: none; margin-bottom: .6rem; }
.bpt-back:hover { color: var(--brass); }
.bpt-eyebrow { font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--brass); margin-bottom: .2rem; }
.bpt-title { font-size: 1.3rem; font-weight: 700; margin: 0; color: var(--ink); }
.bpt-subtitle { font-size: .8rem; color: var(--ink-faint); margin-top: .2rem; max-width: 640px; }

.bpt-alert { border-radius: 6px; font-size: .84rem; padding: .7rem 1rem; margin: 1rem 0; background: var(--active-bg); color: #2F6F4E; border: 1px solid #C7E0CD; }

.bpt-card { background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 1.4rem; margin: 1rem 0; position: relative; }
.bpt-card::before { content: ""; position: absolute; top: 0; left: 26px; width: 56px; height: 5px; background: var(--brass); border-radius: 0 0 3px 3px; }
.bpt-section-label { display: flex; align-items: baseline; gap: .55rem; font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--ink); border-bottom: 1px solid var(--line); padding-bottom: .7rem; margin: .3rem 0 1.1rem; }
.bpt-section-no { font-family: 'IBM Plex Mono', monospace; font-size: .68rem; font-weight: 600; color: #fff; background: var(--brass); padding: .05rem .4rem; border-radius: 3px; }
.bpt-field-hint { font-size: .74rem; color: var(--ink-faint); }

/* templates list */
.bpt-template-row { display: flex; align-items: center; justify-content: space-between; padding: .7rem 0; border-bottom: 1px solid var(--line); gap: 1rem; flex-wrap: wrap; }
.bpt-template-row:last-child { border-bottom: none; }
.bpt-template-name { font-weight: 700; font-size: .88rem; color: var(--ink); display: flex; align-items: center; gap: .5rem; }
.bpt-template-meta { font-size: .74rem; color: var(--ink-faint); margin-top: .15rem; }
.bpt-badge-default { font-size: .62rem; font-weight: 700; background: var(--active-bg); color: #2F6F4E; padding: .15rem .5rem; border-radius: 20px; }
.bpt-badge-source { font-size: .62rem; font-weight: 700; background: #F1F5F9; color: #64748B; padding: .15rem .5rem; border-radius: 20px; text-transform: uppercase; }
.bpt-template-actions { display: flex; gap: .5rem; }
.bpt-template-actions form { margin: 0; }
.bpt-empty { color: var(--ink-faint); font-size: .84rem; padding: .5rem 0; }

/* bank chips */
.bpt-bank-grid { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.2rem; }
.bpt-bank-chip input { position: absolute; opacity: 0; width: 0; height: 0; }
.bpt-bank-chip span { display: inline-flex; align-items: center; padding: .45rem .9rem; border: 1px solid var(--line); border-radius: 20px; font-size: .78rem; font-weight: 600; color: var(--ink-soft); cursor: pointer; }
.bpt-bank-chip input:checked + span { background: var(--brass); color: #fff; border-color: var(--brass); }

/* fields / columns */
.bpt-field-row { display: flex; align-items: center; gap: .7rem; margin-bottom: .8rem; flex-wrap: wrap; }
.bpt-field-label { font-size: .78rem; font-weight: 600; color: var(--ink-soft); min-width: 130px; }
.bpt-input { font-size: .82rem; padding: .5rem .7rem; border: 1px solid var(--line); border-radius: 5px; outline: none; flex: 1; min-width: 140px; }
.bpt-input:focus { border-color: var(--brass); }
.bpt-input-narrow { flex: 0 0 160px; }

.bpt-col-row { display: flex; align-items: center; gap: .5rem; margin-bottom: .5rem; flex-wrap: wrap; }
.bpt-col-remove { background: none; border: none; color: var(--warn); cursor: pointer; padding: .4rem; }

.bpt-upload-row { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }

.bpt-actions { display: flex; justify-content: flex-end; gap: .5rem; margin-top: 1rem; }

/* buttons */
.bpt-btn { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; font-weight: 600; padding: .5rem .95rem; border-radius: 6px; border: 1px solid transparent; cursor: pointer; }
.bpt-btn-primary { background: var(--ink); color: #fff; border-color: var(--ink); }
.bpt-btn-primary:hover { background: #23374F; }
.bpt-btn-outline { background: #fff; color: var(--ink-soft); border-color: var(--line); }
.bpt-btn-outline:hover { border-color: var(--brass); color: var(--brass); }
.bpt-btn-warn { background: #fff; color: var(--warn); border-color: #E3C6BC; }
.bpt-btn-warn:hover { background: var(--warn-bg); }
</style>

<script>
const bptGenericColumns = @json($genericColumns);

function bptColumnRowHtml(col) {
    const tpl = document.getElementById('bptColumnRowTemplate').content.cloneNode(true);
    const row = tpl.querySelector('.bpt-col-row');
    row.querySelector('.bpt-col-header').value = col.header || '';
    row.querySelector('.bpt-col-field').value = col.field || 'static';
    row.querySelector('.bpt-col-prefix').value = col.prefix || '';
    row.querySelector('.bpt-col-static').value = col.static_value || '';
    row.querySelector('.bpt-col-field').addEventListener('change', function () {
        row.querySelector('.bpt-col-static').style.display = this.value === 'static' ? 'block' : 'none';
    });
    if (col.field === 'static') row.querySelector('.bpt-col-static').style.display = 'block';
    return row;
}

function bptRenderColumns(containerId, columns) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    columns.forEach(col => container.appendChild(bptColumnRowHtml(col)));
}

function bptAddColumn(containerId) {
    document.getElementById(containerId).appendChild(
        bptColumnRowHtml({ header: '', field: 'static', prefix: '', static_value: '' })
    );
}

function bptSelectPresetBank(bankId, bankName) {
    document.getElementById('bptPresetBankId').value = bankId ?? '';
    document.getElementById('bptPresetName').value = bankName + ' Bulk Payment Template';
    bptRenderColumns('bptPresetColumns', bptGenericColumns);
}

// Render generic columns on first load
document.addEventListener('DOMContentLoaded', () => bptRenderColumns('bptPresetColumns', bptGenericColumns));

function bptCollectColumns(containerId) {
    return Array.from(document.querySelectorAll(`#${containerId} .bpt-col-row`)).map(row => ({
        header: row.querySelector('.bpt-col-header').value,
        field: row.querySelector('.bpt-col-field').value,
        prefix: row.querySelector('.bpt-col-prefix').value,
        static_value: row.querySelector('.bpt-col-static').value,
    }));
}

// Inject hidden inputs for columns[] before either form submits
document.getElementById('bptPresetForm').addEventListener('submit', function () {
    bptInjectColumns(this, 'bptPresetColumns');
});
document.getElementById('bptCustomForm').addEventListener('submit', function () {
    bptInjectColumns(this, 'bptCustomColumns');
});

function bptInjectColumns(form, containerId) {
    form.querySelectorAll('input[name^="columns"]').forEach(el => el.remove());
    bptCollectColumns(containerId).forEach((col, i) => {
        Object.entries(col).forEach(([key, val]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `columns[${i}][${key}]`;
            input.value = val ?? '';
            form.appendChild(input);
        });
    });
}

function bptParseUpload() {
    const fileInput = document.getElementById('bptCustomFile');
    if (!fileInput.files.length) { alert('Choose a CSV file first.'); return; }

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('_token', document.querySelector('#bptCustomForm input[name=_token]').value);

    fetch('{{ route('bank-templates.custom.parse') }}', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (!data.success) { alert('Could not read that file.'); return; }
            document.getElementById('bptCustomForm').style.display = 'block';
            bptRenderColumns('bptCustomColumns', data.columns);
        });
}
</script>

@endsection
