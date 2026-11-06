{{-- Signature: draw (mouse, touch or stylus) or upload a photo. Fills method + signature_data / signature_file. --}}
@php $sp = $id ?? 'sig'; @endphp
<div class="cc-sig" id="{{ $sp }}">
    <div class="cc-tabs" role="tablist">
        <button type="button" class="on" data-mode="draw" role="tab" aria-selected="true"><i class="bi bi-pen"></i> Draw</button>
        <button type="button" data-mode="upload" role="tab" aria-selected="false"><i class="bi bi-image"></i> Upload a photo</button>
    </div>
    <input type="hidden" name="{{ $methodName ?? 'method' }}" value="draw" data-method>
    <input type="hidden" name="signature_data" data-data>
    <div data-pane="draw">
        <div class="cc-sigbox"><canvas aria-label="Signature pad: draw your signature"></canvas><div class="cc-sigline"></div><span class="hint">Sign above the line with your finger, stylus or mouse</span></div>
        <div style="display:flex;gap:.5rem;margin-top:.5rem;flex-wrap:wrap">
            <button type="button" class="pp-btn pp-btn-ghost pp-btn-sm" data-clear><i class="bi bi-eraser"></i> Clear</button>
        </div>
    </div>
    <div data-pane="upload" hidden>
        <div class="pp-field"><label>Photo or scan of your signature <small>PNG or JPG, up to 5 MB</small></label>
            <input type="file" class="pp-input" name="signature_file" accept="image/png,image/jpeg" data-file></div>
        <small class="pp-muted">Sign on plain white paper and take a clear photo. We remove the paper background for you.</small>
    </div>
    <div style="margin-top:.7rem">
        <div class="pp-muted" style="font-size:.8rem;font-weight:700;margin-bottom:.3rem">Preview</div>
        <div class="cc-sigpreview" data-preview><span class="pp-muted" style="font-size:.85rem">Your signature will appear here</span></div>
    </div>
    @error('signature')<div class="pp-error" style="color:var(--zp-red);font-size:.85rem;margin-top:.4rem">{{ $message }}</div>@enderror
    @error('signature_file')<div class="pp-error" style="color:var(--zp-red);font-size:.85rem;margin-top:.4rem">{{ $message }}</div>@enderror
</div>
@push('scripts')
<script>
(() => {
  const root = document.getElementById(@json($sp));
  const canvas = root.querySelector('canvas'), ctx = canvas.getContext('2d');
  const method = root.querySelector('[data-method]'), data = root.querySelector('[data-data]'), preview = root.querySelector('[data-preview]');
  const file = root.querySelector('[data-file]');
  let drawing = false, last = null, inked = false;
  const size = () => {
    const r = canvas.getBoundingClientRect(), dpr = window.devicePixelRatio || 1;
    const keep = inked ? canvas.toDataURL() : null;
    canvas.width = Math.round(r.width * dpr); canvas.height = Math.round(r.height * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#10231a';
    if (keep) { const i = new Image(); i.onload = () => ctx.drawImage(i, 0, 0, r.width, r.height); i.src = keep; }
  };
  const pos = e => { const r = canvas.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top, p: e.pressure || .5 }; };
  canvas.addEventListener('pointerdown', e => { drawing = true; last = pos(e); canvas.setPointerCapture(e.pointerId); e.preventDefault(); });
  canvas.addEventListener('pointermove', e => {
    if (!drawing) return; const p = pos(e);
    ctx.lineWidth = Math.max(1.4, Math.min(3.6, (e.pointerType === 'pen' ? p.p * 4 : 2.4)));
    ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke(); last = p; inked = true; e.preventDefault();
  });
  const end = () => { if (!drawing) return; drawing = false; if (inked) { data.value = canvas.toDataURL('image/png'); preview.innerHTML = '<img alt="Your drawn signature" src="' + data.value + '">'; } };
  canvas.addEventListener('pointerup', end); canvas.addEventListener('pointercancel', end); canvas.addEventListener('pointerleave', end);
  root.querySelector('[data-clear]').addEventListener('click', () => { ctx.clearRect(0, 0, canvas.width, canvas.height); inked = false; data.value = ''; preview.innerHTML = '<span class="pp-muted" style="font-size:.85rem">Your signature will appear here</span>'; });
  root.querySelectorAll('[data-mode]').forEach(b => b.addEventListener('click', () => {
    root.querySelectorAll('[data-mode]').forEach(x => { x.classList.toggle('on', x === b); x.setAttribute('aria-selected', x === b); });
    root.querySelectorAll('[data-pane]').forEach(p => p.hidden = p.dataset.pane !== b.dataset.mode);
    method.value = b.dataset.mode; if (b.dataset.mode === 'draw') size();
  }));
  // Preview an upload with its light background removed (the server does the same when it stores it).
  file.addEventListener('change', () => {
    const f = file.files[0]; if (!f) return;
    const img = new Image(); img.onload = () => {
      const c = document.createElement('canvas'), s = Math.min(1, 900 / Math.max(img.width, img.height));
      c.width = img.width * s; c.height = img.height * s; const x = c.getContext('2d'); x.drawImage(img, 0, 0, c.width, c.height);
      const d = x.getImageData(0, 0, c.width, c.height), px = d.data; let bg = 0, n = 0;
      for (let i = 0; i < px.length; i += 4 * 97) { bg += .299 * px[i] + .587 * px[i + 1] + .114 * px[i + 2]; n++; }
      const th = Math.max(60, bg / n - 45);
      for (let i = 0; i < px.length; i += 4) { const l = .299 * px[i] + .587 * px[i + 1] + .114 * px[i + 2]; if (l >= th) px[i + 3] = 0; }
      x.putImageData(d, 0, 0); preview.innerHTML = '<img alt="Your uploaded signature" src="' + c.toDataURL() + '">';
    }; img.src = URL.createObjectURL(f);
  });
  window.addEventListener('resize', size); size();
})();
</script>
@endpush
