@extends('layouts.app')
@section('title', 'Upload documents · ZamPayroll')
@include('compliance.partials.head')
@section('content')
<div class="pr pp cc">
    <div class="pr-head"><div><h1>Upload documents</h1><p>PDF, Word, Excel, JPG or PNG, up to {{ $maxMb }} MB each. Upload several at once, or a ZIP of them. The details below apply to every file in this upload.</p></div>
        <div class="pr-head-actions"><a class="pp-btn pp-btn-ghost" href="{{ route('compliance.documents.index') }}"><i class="bi bi-arrow-left"></i> Documents</a></div></div>
    <form method="POST" action="{{ route('compliance.documents.store') }}" enctype="multipart/form-data" class="pr-card" id="upForm" style="max-width:980px">
        @csrf
        <label class="cc-drop" id="drop" for="files"><i class="bi bi-cloud-arrow-up"></i><div style="font-weight:800;margin-top:.3rem">Drag files here or click to choose</div><small class="pp-muted">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG or ZIP</small></label>
        <input type="file" id="files" name="files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip" hidden>
        <ul class="cc-files" id="fileList"></ul>
        @error('files')<div class="cc-alert danger" style="margin-top:.6rem"><i class="bi bi-exclamation-octagon lead"></i><div>{{ $message }}</div></div>@enderror
        <div id="upError" class="cc-alert danger" style="margin-top:.6rem" hidden><i class="bi bi-exclamation-octagon lead"></i><div></div></div>
        <div style="margin-top:1rem">@include('compliance.partials.document-meta', ['doc' => null, 'withTitle' => true, 'titleHint' => '(used when you upload one file; otherwise each file keeps its name)'])</div>
        <div class="cc-progress" id="prog"><span></span></div>
        <div style="margin-top:1rem"><button class="pp-btn pp-btn-primary" id="upBtn"><i class="bi bi-cloud-upload"></i> Upload</button></div>
    </form>
</div>
@push('scripts')
<script>
(() => {
  const form = document.getElementById('upForm'), input = document.getElementById('files'), drop = document.getElementById('drop');
  const list = document.getElementById('fileList'), prog = document.getElementById('prog'), bar = prog.querySelector('span'), err = document.getElementById('upError');
  const max = {{ $maxMb }} * 1024 * 1024, ok = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','zip'];
  let files = [];
  const render = () => { list.innerHTML = ''; files.forEach((f, i) => {
    const ext = f.name.split('.').pop().toLowerCase(), bad = !ok.includes(ext) ? 'Not a supported type' : (f.size > max * (ext === 'zip' ? 5 : 1) ? 'Too large' : '');
    const li = document.createElement('li'); li.innerHTML = `<span>${f.name.replace(/[<>&]/g, '')} <small class="pp-muted">${(f.size / 1048576).toFixed(1)} MB</small></span><span>${bad ? '<b style="color:var(--zp-red)">' + bad + '</b>' : '<i class="bi bi-check2" style="color:var(--zp-green)"></i>'} <button type="button" data-i="${i}" class="pp-btn pp-btn-ghost pp-btn-sm" aria-label="Remove">×</button></span>`;
    list.appendChild(li); }); };
  const add = fl => { files = files.concat([...fl]).slice(0, 25); render(); };
  input.addEventListener('change', () => add(input.files));
  list.addEventListener('click', e => { const b = e.target.closest('[data-i]'); if (b) { files.splice(+b.dataset.i, 1); render(); } });
  ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('over'); }));
  ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('over'); }));
  drop.addEventListener('drop', e => add(e.dataTransfer.files));
  form.addEventListener('submit', e => {
    e.preventDefault(); err.hidden = true;
    if (!files.length) { err.hidden = false; err.querySelector('div').textContent = 'Choose at least one file.'; return; }
    const fd = new FormData(form); fd.delete('files[]'); files.forEach(f => fd.append('files[]', f));
    const xhr = new XMLHttpRequest(); xhr.open('POST', form.action); xhr.setRequestHeader('Accept', 'application/json'); xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    prog.style.display = 'block'; document.getElementById('upBtn').disabled = true;
    xhr.upload.onprogress = ev => { if (ev.lengthComputable) bar.style.width = Math.round(ev.loaded / ev.total * 100) + '%'; };
    xhr.onload = () => {
      document.getElementById('upBtn').disabled = false;
      let r = {}; try { r = JSON.parse(xhr.responseText); } catch (x) {}
      if (xhr.status >= 200 && xhr.status < 300 && r.redirect) { bar.style.width = '100%'; window.location = r.redirect; return; }
      prog.style.display = 'none'; bar.style.width = 0; err.hidden = false;
      err.querySelector('div').textContent = r.errors ? Object.values(r.errors).flat().join(' ') : (r.message || 'The upload failed. Please try again.');
    };
    xhr.onerror = () => { document.getElementById('upBtn').disabled = false; err.hidden = false; err.querySelector('div').textContent = 'Network error. Please try again.'; };
    xhr.send(fd);
  });
})();
</script>
@endpush
@endsection
