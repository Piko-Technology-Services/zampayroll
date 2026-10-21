@extends('layouts.app')

@section('title', 'Import employees · ZamPayroll')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/zp-people.css') }}">
@endpush

@section('content')
<div class="pp" id="ppImport"
     data-parse="{{ route('employees.import.parse') }}"
     data-preview="{{ route('employees.import.preview') }}"
     data-commit="{{ route('employees.import.commit') }}">

    <section class="pp-hero">
        <div class="pp-eyebrow">People · Bring your team</div>
        <h1>Import your whole team in three steps</h1>
        <p>Drop the spreadsheet from your old payroll system (Sage, Pastel, QuickBooks, Excel, anything), or just paste a message with your people in it. We sort it out, you check the list, done.</p>
    </section>

    <ol class="pp-steps" id="ppSteps">
        <li class="pp-step active" data-step="1"><span class="n">1</span><span class="t">Add your list</span></li>
        <li class="pp-step" data-step="2"><span class="n">2</span><span class="t">Match columns</span></li>
        <li class="pp-step" data-step="3"><span class="n">3</span><span class="t">Check &amp; import</span></li>
        <li class="pp-step" data-step="4"><span class="n"><i class="bi bi-check-lg"></i></span><span class="t">Done</span></li>
    </ol>

    <div class="pp-error" id="ppError" hidden></div>

    {{-- STEP 1 — source --}}
    <div class="pp-panel" data-panel="1">
        <div class="pp-sources">
            <label class="pp-drop" id="ppDrop">
                <input type="file" id="ppFile" accept=".xlsx,.xls,.ods,.csv,.tsv,.txt">
                <div class="pp-drop-icon"><i class="bi bi-file-earmark-spreadsheet"></i></div>
                <strong id="ppDropTitle">Drop your Excel or CSV file here</strong>
                <div class="pp-muted">Any layout — titles, several sheets, odd column names · up to {{ number_format(\App\Http\Controllers\EmployeeImportController::MAX_ROWS) }} people</div>
                <div class="pp-formats"><span>.XLSX</span><span>.XLS</span><span>.CSV</span></div>
            </label>

            <div class="pp-card pp-paste">
                <div class="pp-card-title" style="margin-bottom:.6rem">
                    <h3><i class="bi bi-clipboard-check me-1"></i> …or paste anything</h3>
                </div>
                <p class="pp-muted" style="margin:0 0 .6rem">Rows copied from Excel, a WhatsApp message, an email, your notes. We pick out names, NRCs, phones, emails, pay, banks and job titles. Put each person on their own line or paragraph.</p>
                <textarea id="ppPaste" placeholder="Chanda Mwila, NRC 123456/10/1, 0977 123 456, accountant in Finance, net K8,500, Zanaco 0123456789012&#10;&#10;Bwalya Phiri – driver, 0966 555 111, born 12/03/1990, basic 4500"></textarea>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.7rem;gap:.6rem;flex-wrap:wrap">
                    <a class="pp-link" href="{{ route('employees.import.template') }}"><i class="bi bi-download"></i> Download a blank template</a>
                    <button class="pp-btn pp-btn-primary" type="button" id="ppPasteGo">Continue <i class="bi bi-arrow-right"></i></button>
                </div>
            </div>
        </div>

        <div class="pp-tips">
            <div class="pp-tip"><i class="bi bi-magic"></i><div><b>Any layout</b>"Surname", "Man No", "Take home", headings on row 5, one sheet per branch, or no headings at all. We work it out.</div></div>
            <div class="pp-tip"><i class="bi bi-shield-check"></i><div><b>No double entries</b>People already on your team (same NRC, email or name) are spotted for you.</div></div>
            <div class="pp-tip"><i class="bi bi-hourglass-split"></i><div><b>Only names are required</b>Missing NRC, TPIN or bank details? Import now and finish profiles later.</div></div>
        </div>
    </div>

    {{-- STEP 2 — mapping --}}
    <div class="pp-panel" data-panel="2" hidden>
        <div class="pp-journey">
            <div class="pp-card">
                <div class="pp-card-title">
                    <div>
                        <h2>Match your columns</h2>
                        <div class="pp-muted"><span id="ppSource"></span> · <span id="ppRowCount"></span> people found. We matched what we could — change anything that looks wrong.</div>
                        <ul class="pp-notes" id="ppNotes" hidden></ul>
                    </div>
                </div>
                <div class="pp-maplist" id="ppMapList"></div>
            </div>
            <div class="pp-side pp-mapside">
                <div class="pp-meter">
                    <div class="pp-meter-head"><span>What we'll bring in</span><b id="ppMatchedCount">0</b></div>
                    <ul class="pp-need" id="ppNeed"></ul>
                    <div class="pp-callout green" style="margin-top:.9rem"><i class="bi bi-lightbulb"></i><div>Only first &amp; last name (or a full-name column) are required. Everything else can be added later.</div></div>
                </div>
                <div style="display:flex;gap:.5rem">
                    <button class="pp-btn pp-btn-ghost" type="button" data-goto="1"><i class="bi bi-arrow-left"></i> Back</button>
                    <button class="pp-btn pp-btn-primary" type="button" id="ppToReview" style="flex:1">Check the list <i class="bi bi-arrow-right"></i></button>
                </div>
            </div>
        </div>
    </div>

    {{-- STEP 3 — review --}}
    <div class="pp-panel" data-panel="3" hidden>
        <div class="pp-card">
            <div class="pp-card-title">
                <div>
                    <h2>Check everyone before we import</h2>
                    <div class="pp-muted">Click any cell to fix it. Untick anyone you don't want to bring in.</div>
                </div>
                <button class="pp-btn pp-btn-ghost pp-btn-sm" type="button" data-goto="2"><i class="bi bi-arrow-left"></i> Columns</button>
            </div>

            <div class="pp-summary" id="ppSummary">
                <button class="pp-sum ready" data-filter="ready" type="button"><b>0</b><span>Ready</span></button>
                <button class="pp-sum warning" data-filter="warning" type="button"><b>0</b><span>Need details later</span></button>
                <button class="pp-sum duplicate" data-filter="duplicate" type="button"><b>0</b><span>Already on your team</span></button>
                <button class="pp-sum error" data-filter="error" type="button"><b>0</b><span>Can't import yet</span></button>
            </div>

            <div class="pp-tablewrap"><table class="pp-table" id="ppTable"></table></div>

            <div class="pp-toolbar">
                <div style="display:flex;gap:1.2rem;flex-wrap:wrap;align-items:center">
                    <div>
                        <div class="pp-muted" style="font-size:.74rem;font-weight:600;margin-bottom:.25rem">People already on your team</div>
                        <div class="pp-seg">
                            <label><input type="radio" name="ppDup" value="skip" checked><span>Skip them</span></label>
                            <label><input type="radio" name="ppDup" value="update"><span>Update their details</span></label>
                        </div>
                    </div>
                    @if(auth()->user()?->hasPermission('portal.manage'))
                    <label class="pp-toggle"><input type="checkbox" id="ppPortals"> Create self-service portals &amp; email logins</label>
                    @endif
                </div>
                <button class="pp-btn pp-btn-primary" type="button" id="ppCommit"><i class="bi bi-cloud-arrow-up"></i> <span>Import</span></button>
            </div>
        </div>
    </div>

    {{-- STEP 4 — done --}}
    <div class="pp-panel" data-panel="4" hidden>
        <div class="pp-card pp-done">
            <div class="pp-done-badge"><i class="bi bi-people-fill"></i></div>
            <h2 id="ppDoneTitle">Your team is in!</h2>
            <p class="pp-muted" id="ppDoneSub"></p>
            <div class="pp-done-stats" id="ppDoneStats"></div>
            <div class="pp-problems" id="ppProblems" hidden></div>
            <div style="display:flex;gap:.6rem;justify-content:center;flex-wrap:wrap">
                <a class="pp-btn pp-btn-primary" href="{{ route('employees.index') }}"><i class="bi bi-people"></i> See all employees</a>
                <button class="pp-btn pp-btn-ghost" type="button" onclick="location.reload()"><i class="bi bi-upload"></i> Import another list</button>
                @if(auth()->user()?->hasPermission('payroll.run'))
                <a class="pp-btn pp-btn-ghost" href="{{ route('payroll.runs.create') }}"><i class="bi bi-cash-stack"></i> Run payroll</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/zp-people.js') }}"></script>
<script>
(function () {
    const FIELD_GROUPS = @json($fieldOptions);
    const LABEL = {};
    Object.values(FIELD_GROUPS).forEach(g => Object.assign(LABEL, g));
    const ORDER = Object.keys(LABEL);
    const root = document.getElementById('ppImport');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (s, r = document) => r.querySelector(s);
    const esc = zpPeople.esc;

    const state = { headers: [], samples: {}, mapping: {}, raw: [], rows: [], filter: null };

    function showError(msg) { const e = $('#ppError'); e.textContent = msg || ''; e.hidden = !msg; if (msg) e.scrollIntoView({ behavior: 'smooth', block: 'center' }); }

    function go(step) {
        root.querySelectorAll('[data-panel]').forEach(p => p.hidden = +p.dataset.panel !== step);
        root.querySelectorAll('.pp-step').forEach(s => {
            const n = +s.dataset.step;
            s.classList.toggle('active', n === step);
            s.classList.toggle('done', n < step);
        });
        showError('');
        window.scrollTo({ top: root.offsetTop - 20, behavior: 'smooth' });
    }
    root.addEventListener('click', e => { const b = e.target.closest('[data-goto]'); if (b) go(+b.dataset.goto); });

    async function post(url, body, isForm) {
        const res = await fetch(url, {
            method: 'POST',
            headers: Object.assign({ 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, isForm ? {} : { 'Content-Type': 'application/json' }),
            body: isForm ? body : JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const first = data.errors ? Object.values(data.errors)[0][0] : null;
            throw new Error(data.message && !first ? data.message : (first || data.message || 'Something went wrong. Please try again.'));
        }
        return data;
    }

    /* ---------- Step 1 ---------- */
    async function parse(fd, label) {
        $('#ppDropTitle').innerHTML = '<span class="pp-busy" style="border-color:rgba(0,116,45,.25);border-top-color:#00742D"></span> Reading ' + esc(label) + '…';
        try {
            const d = await post(root.dataset.parse, fd, true);
            Object.assign(state, { headers: d.headers, samples: d.samples, mapping: d.mapping, raw: d.rows });
            $('#ppSource').textContent = d.source;
            $('#ppRowCount').textContent = d.rows.length;
            const notes = $('#ppNotes');
            notes.innerHTML = (d.notes || []).map(n => '<li><i class="bi bi-info-circle"></i> ' + esc(n) + '</li>').join('');
            notes.hidden = !(d.notes || []).length;
            renderMap();
            go(2);
        } catch (err) { showError(err.message); }
        $('#ppDropTitle').textContent = 'Drop your Excel or CSV file here';
    }
    const fileInput = $('#ppFile'), drop = $('#ppDrop');
    fileInput.addEventListener('change', () => {
        if (!fileInput.files[0]) return;
        const fd = new FormData(); fd.append('file', fileInput.files[0]);
        parse(fd, fileInput.files[0].name); fileInput.value = '';
    });
    ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('over'); }));
    ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, () => drop.classList.remove('over')));
    drop.addEventListener('drop', e => {
        e.preventDefault();
        const f = e.dataTransfer.files[0]; if (!f) return;
        const fd = new FormData(); fd.append('file', f); parse(fd, f.name);
    });
    $('#ppPasteGo').addEventListener('click', () => {
        const t = $('#ppPaste').value.trim();
        if (!t) { showError('Paste your list first. Any format works.'); return; }
        const fd = new FormData(); fd.append('paste', t); parse(fd, 'your list');
    });

    /* ---------- Step 2 ---------- */
    function optionsHtml(selected) {
        let h = '<option value="">— Don\'t import this column —</option>';
        for (const [group, fields] of Object.entries(FIELD_GROUPS)) {
            h += '<optgroup label="' + esc(group) + '">';
            for (const [f, l] of Object.entries(fields)) h += '<option value="' + f + '"' + (f === selected ? ' selected' : '') + '>' + esc(l) + '</option>';
            h += '</optgroup>';
        }
        return h;
    }
    function renderMap() {
        $('#ppMapList').innerHTML = state.headers.map((h, i) => {
            const f = state.mapping[h];
            const eg = (state.samples[h] || []).join(' · ');
            return '<div class="pp-maprow ' + (f ? 'matched' : 'ignored') + '">' +
                '<div><div class="col">' + esc(h) + '</div><div class="eg">' + (eg ? 'e.g. ' + esc(eg) : 'empty column') + '</div></div>' +
                '<div class="arrow"><i class="bi ' + (f ? 'bi-check-circle-fill' : 'bi-arrow-right') + '"></i></div>' +
                '<select class="pp-select" data-h="' + i + '">' + optionsHtml(f) + '</select></div>';
        }).join('');
        renderNeed();
    }
    $('#ppMapList').addEventListener('change', e => {
        const s = e.target.closest('select'); if (!s) return;
        const h = state.headers[+s.dataset.h], v = s.value || null;
        if (v) for (const k in state.mapping) if (state.mapping[k] === v && k !== h) state.mapping[k] = null; // one column per field
        state.mapping[h] = v;
        renderMap();
    });
    function mappedFields() { return Object.values(state.mapping).filter(Boolean); }
    function renderNeed() {
        const m = mappedFields();
        const hasName = m.includes('name') || (m.includes('first_name') && m.includes('last_name'));
        const items = [
            [hasName, 'Names', true],
            [m.includes('nrc_no'), 'NRC'],
            [m.includes('net_salary') || m.includes('salary'), 'Pay'],
            [m.includes('bank_account_no'), 'Bank account'],
            [m.includes('tpin') || m.includes('ssn'), 'TPIN / NAPSA'],
            [m.includes('primary_phone') || m.includes('company_email'), 'Phone / email'],
        ];
        $('#ppNeed').innerHTML = items.map(([ok, l, req]) => '<li class="' + (ok ? 'ok' : '') + '"><i class="bi ' + (ok ? 'bi-check-circle-fill' : (req ? 'bi-exclamation-circle' : 'bi-circle')) + '"></i>' + l + (req && !ok ? ' <small style="color:#C42926">required</small>' : '') + '</li>').join('');
        $('#ppMatchedCount').textContent = m.length + ' of ' + state.headers.length;
        $('#ppToReview').disabled = !hasName;
    }

    /* ---------- Step 3 ---------- */
    function buildRows() {
        return state.raw.map(r => {
            const d = {};
            for (const [h, f] of Object.entries(state.mapping)) if (f && r[h] !== undefined && String(r[h]).trim() !== '' && d[f] === undefined) d[f] = String(r[h]).trim();
            if (d.name && !d.first_name) { // split full names so they can be edited
                const p = d.name.split(/\s+/); d.first_name = p.shift(); d.last_name = p.pop() || ''; if (p.length) d.middle_name = p.join(' ');
            }
            delete d.name;
            return { data: d, include: true };
        });
    }
    function columns() {
        const set = new Set(); state.rows.forEach(r => Object.keys(r.data).forEach(k => set.add(k)));
        ['first_name', 'last_name'].forEach(k => set.add(k));
        return ORDER.filter(f => set.has(f));
    }
    async function check() {
        const btn = $('#ppToReview'); const old = btn.innerHTML;
        btn.innerHTML = '<span class="pp-busy"></span> Checking…'; btn.disabled = true;
        try {
            const d = await post(root.dataset.preview, { rows: state.rows.map(r => r.data) });
            d.rows.forEach((res, i) => {
                const r = state.rows[i];
                const wasError = r.status === 'error';
                Object.assign(r, { status: res.status, note: res.note });
                if (res.status === 'error') r.include = false; else if (wasError || r.include === undefined) r.include = true;
            });
            renderSummary(d.summary);
            return true;
        } catch (err) { showError(err.message); return false; }
        finally { btn.innerHTML = old; btn.disabled = false; }
    }
    $('#ppToReview').addEventListener('click', async () => {
        state.rows = buildRows(); state.filter = null;
        if (await check()) { renderTable(); go(3); }
    });
    function renderSummary(s) {
        for (const k of ['ready', 'warning', 'duplicate', 'error']) {
            const b = $('.pp-sum.' + k + ' b'); b.textContent = s[k];
            $('.pp-sum.' + k).classList.toggle('on', state.filter === k);
        }
        updateCommit();
    }
    const STATUS = { ready: ['bi-check-circle-fill', 'Ready'], warning: ['bi-hourglass-split', 'Details later'], duplicate: ['bi-person-check', 'On your team'], error: ['bi-x-circle', "Can't import"] };
    function renderTable() {
        const cols = columns();
        let h = '<thead><tr><th><input type="checkbox" id="ppAll" checked title="Include all"></th><th>#</th><th>Status</th>' + cols.map(c => '<th>' + esc(LABEL[c] || c) + '</th>').join('') + '</tr></thead><tbody>';
        state.rows.forEach((r, i) => {
            if (state.filter && r.status !== state.filter) return;
            const [ic, lbl] = STATUS[r.status] || STATUS.ready;
            h += '<tr data-i="' + i + '" class="' + (r.include ? '' : 'off') + '">' +
                '<td><input type="checkbox" class="pp-inc" ' + (r.include ? 'checked' : '') + (r.status === 'error' ? ' disabled' : '') + '></td>' +
                '<td class="pp-muted">' + (i + 1) + '</td>' +
                '<td><span class="pp-badge ' + r.status + '"><i class="bi ' + ic + '"></i>' + lbl + '</span><div class="pp-note">' + esc(r.note) + '</div></td>' +
                cols.map(c => {
                    const bad = r.status === 'error' && (c === 'first_name' || c === 'last_name') && !r.data[c];
                    return '<td><input class="pp-cell' + (bad ? ' bad' : '') + '" data-f="' + c + '" value="' + esc(r.data[c] ?? '') + '" placeholder="—"></td>';
                }).join('') + '</tr>';
        });
        $('#ppTable').innerHTML = h + '</tbody>';
        updateCommit();
    }
    let timer;
    $('#ppTable').addEventListener('input', e => {
        const c = e.target.closest('.pp-cell'); if (!c) return;
        const r = state.rows[+c.closest('tr').dataset.i];
        r.data[c.dataset.f] = c.value;
        clearTimeout(timer);
        timer = setTimeout(async () => { if (await check()) renderTable(); }, 900);
    });
    $('#ppTable').addEventListener('change', e => {
        if (e.target.id === 'ppAll') {
            state.rows.forEach(r => { if (r.status !== 'error' && (!state.filter || r.status === state.filter)) r.include = e.target.checked; });
            renderTable(); return;
        }
        if (e.target.classList.contains('pp-inc')) {
            const tr = e.target.closest('tr'); state.rows[+tr.dataset.i].include = e.target.checked;
            tr.classList.toggle('off', !e.target.checked); updateCommit();
        }
    });
    $('#ppSummary').addEventListener('click', e => {
        const b = e.target.closest('[data-filter]'); if (!b) return;
        state.filter = state.filter === b.dataset.filter ? null : b.dataset.filter;
        root.querySelectorAll('.pp-sum').forEach(s => s.classList.toggle('on', s.dataset.filter === state.filter));
        renderTable();
    });
    function updateCommit() {
        const n = state.rows.filter(r => r.include && r.status !== 'error').length;
        const dupSkip = document.querySelector('input[name="ppDup"]:checked').value === 'skip';
        const dups = state.rows.filter(r => r.include && r.status === 'duplicate').length;
        const real = dupSkip ? n - dups : n;
        $('#ppCommit span').textContent = real ? 'Import ' + real + (real === 1 ? ' person' : ' people') : 'Nothing to import';
        $('#ppCommit').disabled = !real;
    }
    document.querySelectorAll('input[name="ppDup"]').forEach(r => r.addEventListener('change', updateCommit));

    $('#ppCommit').addEventListener('click', async () => {
        const btn = $('#ppCommit'); const old = btn.innerHTML;
        btn.innerHTML = '<span class="pp-busy"></span> Importing…'; btn.disabled = true;
        try {
            const d = await post(root.dataset.commit, {
                rows: state.rows.filter(r => r.include && r.status !== 'error').map(r => r.data),
                duplicates: document.querySelector('input[name="ppDup"]:checked').value,
                create_portals: !!(document.getElementById('ppPortals') || {}).checked,
            });
            const stats = [[d.created, 'added'], [d.updated, 'updated'], [d.skipped, 'skipped']];
            if (d.portals) stats.push([d.portals, 'portals sent']);
            $('#ppDoneStats').innerHTML = stats.filter(s => s[0] || s[1] === 'added').map(([n, l]) => '<div><b>' + n + '</b><span>' + l + '</span></div>').join('');
            $('#ppDoneTitle').textContent = d.created ? (d.created === 1 ? 'Welcome aboard! 🎉' : d.created + ' people joined your team! 🎉') : 'All done';
            $('#ppDoneSub').textContent = 'You now have ' + d.total + ' employees in ZamPayroll. Missing details show up on each profile so you can finish them anytime.';
            if (d.problems.length) { const p = $('#ppProblems'); p.hidden = false; p.innerHTML = '<b>Not imported:</b><br>' + d.problems.slice(0, 12).map(esc).join('<br>'); }
            go(4);
            if (d.created) zpPeople.confetti();
        } catch (err) { showError(err.message); }
        finally { btn.innerHTML = old; btn.disabled = false; }
    });
})();
</script>
@endpush
