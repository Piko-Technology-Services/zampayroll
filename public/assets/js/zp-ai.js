/* ZamPayroll AI mode — panel behaviour */
(function () {
  const root = document.getElementById('zpai');
  if (!root) return;

  const $ = (id) => document.getElementById(id);
  const panel = $('zpaiPanel'), body = $('zpaiBody'), input = $('zpaiInput'), form = $('zpaiForm');
  const sendBtn = $('zpaiSend'), fileInput = $('zpaiFile'), chip = $('zpaiChip');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  const mark = $('zpaiMarkTpl').innerHTML;
  const urls = {
    message: root.dataset.messageUrl,
    confirm: (t) => root.dataset.confirmUrl.replace('__T__', t),
    cancel: (t) => root.dataset.cancelUrl.replace('__T__', t),
    reset: root.dataset.resetUrl,
  };
  let file = null, busy = false, lastFocus = null;

  if (/Mac|iPhone|iPad/.test(navigator.platform)) $('zpaiKbd').textContent = '⌘ K';

  /* ---------------- open / close ---------------- */
  function open() {
    lastFocus = document.activeElement;
    root.classList.add('open');
    panel.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    setTimeout(() => input.focus(), 250);
  }
  function close() {
    root.classList.remove('open');
    panel.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    lastFocus && lastFocus.focus && lastFocus.focus();
  }
  $('zpaiFab').addEventListener('click', open);
  $('zpaiClose').addEventListener('click', close);
  $('zpaiScrim').addEventListener('click', close);
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); root.classList.contains('open') ? close() : open(); }
    if (e.key === 'Escape' && root.classList.contains('open')) close();
  });
  // Other buttons in the app can open AI mode with a prompt: <button data-ai-prompt="...">
  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-ai-prompt]');
    if (t && !root.contains(t)) { e.preventDefault(); open(); ask(t.dataset.aiPrompt); }
  });

  /* ---------------- composer ---------------- */
  function syncSend() { sendBtn.disabled = busy || (!input.value.trim() && !file); }
  input.addEventListener('input', () => {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 160) + 'px';
    syncSend();
  });
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
  });
  // stopPropagation: the app layout shows a full-page loader on every form submit.
  form.addEventListener('submit', (e) => { e.preventDefault(); e.stopPropagation(); ask(input.value); });

  $('zpaiAttach').addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', () => setFile(fileInput.files[0]));
  $('zpaiChipX').addEventListener('click', () => setFile(null));
  function setFile(f) {
    file = f || null;
    chip.hidden = !file;
    $('zpaiChipName').textContent = file ? file.name : '';
    if (!file) fileInput.value = '';
    syncSend();
    input.focus();
  }

  // drag & drop
  let dragDepth = 0;
  panel.addEventListener('dragenter', (e) => { if (e.dataTransfer.types.includes('Files')) { dragDepth++; $('zpaiDrop').hidden = false; } });
  panel.addEventListener('dragleave', () => { if (--dragDepth <= 0) { dragDepth = 0; $('zpaiDrop').hidden = true; } });
  panel.addEventListener('dragover', (e) => e.preventDefault());
  panel.addEventListener('drop', (e) => {
    e.preventDefault(); dragDepth = 0; $('zpaiDrop').hidden = true;
    if (e.dataTransfer.files[0]) setFile(e.dataTransfer.files[0]);
  });

  body.addEventListener('click', (e) => {
    const s = e.target.closest('[data-ask]');
    if (s) return ask(s.dataset.ask);
    if (e.target.closest('[data-attach]')) {
      input.value = 'Add these employees:\n';
      input.dispatchEvent(new Event('input'));
      fileInput.click();
    }
  });

  $('zpaiNew').addEventListener('click', async () => {
    await post(urls.reset);
    body.querySelectorAll('.zpai-msg').forEach((m) => m.remove());
    $('zpaiHello').hidden = false;
    input.focus();
  });

  /* ---------------- messaging ---------------- */
  async function ask(text) {
    text = (text || '').trim();
    if (busy || (!text && !file)) return;

    $('zpaiHello').hidden = true;
    addUser(text, file);

    const fd = new FormData();
    fd.append('message', text);
    if (file) fd.append('attachment', file);

    input.value = ''; input.style.height = 'auto';
    setFile(null);
    setBusy(true);
    const typing = addTyping();

    try {
      const res = await fetch(urls.message, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, body: fd });
      typing.remove();
      if (res.status === 419) return addBot('Your session expired — refresh the page and try again.', [], true);
      if (res.status === 429) return addBot('You\'re going a bit fast — give me a few seconds and try again.', [], true);
      const data = await res.json();
      if (!res.ok) return addBot(data.message || 'Something went wrong.', [], true);
      addBot(data.reply, data.cards || []);
    } catch (err) {
      typing.remove();
      addBot('I couldn\'t reach the server. Check your connection and try again.', [], true);
    } finally {
      setBusy(false);
    }
  }

  function setBusy(b) { busy = b; root.classList.toggle('busy', b); syncSend(); }

  function addUser(text, f) {
    const el = div('zpai-msg user');
    const bubble = div('bubble');
    bubble.textContent = text || '';
    if (f) bubble.insertAdjacentHTML('beforeend', `<div class="file"><i class="bi bi-file-earmark-spreadsheet"></i>${esc(f.name)}</div>`);
    el.appendChild(bubble);
    push(el);
  }

  function addTyping() {
    const el = div('zpai-msg bot');
    el.innerHTML = `<div class="av">${mark}</div><div class="content"><div class="zpai-typing"><span></span><span></span><span></span></div></div>`;
    push(el);
    return el;
  }

  function addBot(text, cards, isError) {
    const el = div('zpai-msg bot' + (isError ? ' error' : ''));
    el.innerHTML = `<div class="av">${mark}</div><div class="content"><div class="text">${md(text || '')}</div></div>`;
    const content = el.querySelector('.content');
    (cards || []).forEach((c) => content.appendChild(renderCard(c)));
    push(el);
  }

  function push(el) { body.appendChild(el); requestAnimationFrame(() => (body.scrollTop = body.scrollHeight)); }

  /* ---------------- cards ---------------- */
  function renderCard(c) {
    const card = div('zpai-card' + (c.type === 'action' ? ' zpai-action' : ''));
    const head = `<div class="zpai-card-head"><span>${esc(c.title || '')}</span>${c.link ? `<a href="${attr(c.link)}">Open <i class="bi bi-arrow-up-right"></i></a>` : ''}</div>`;

    if (c.type === 'stats') {
      card.innerHTML = head + `<div class="zpai-stats">${(c.items || []).map((i) => {
        const delta = i.delta === null || i.delta === undefined ? '' : `<span class="zpai-delta ${i.delta >= 0 ? 'up' : 'down'}">${i.delta >= 0 ? '▲' : '▼'} ${Math.abs(i.delta)}%</span>`;
        const val = `<b>${esc(String(i.value))}</b>${delta}`;
        return `<div class="zpai-stat"><small>${esc(i.label)}</small>${i.link ? `<a href="${attr(i.link)}">${val}</a>` : val}</div>`;
      }).join('')}</div>`;
    } else if (c.type === 'table') {
      card.innerHTML = head + table(c.columns, c.rows);
    } else if (c.type === 'action') {
      card.innerHTML = `<div class="zpai-card-head"><span><i class="bi bi-lightning-charge"></i> ${esc(c.title)}</span><span>Needs your OK</span></div>`
        + (c.table ? table(c.table.columns, c.table.rows) : '')
        + (c.note ? `<div class="note">${esc(c.note)}</div>` : '')
        + `<div class="btns"><button type="button" class="zpai-btn primary" data-go><i class="bi bi-check2"></i> Confirm</button><button type="button" class="zpai-btn ghost" data-no>Cancel</button></div>`;
      card.querySelector('[data-go]').addEventListener('click', () => runAction(card, c.token));
      card.querySelector('[data-no]').addEventListener('click', async () => {
        card.classList.add('cancelled');
        card.querySelector('.btns').innerHTML = '<div class="zpai-result">Cancelled — nothing was changed.</div>';
        await post(urls.cancel(c.token));
      });
    }
    return card;
  }

  async function runAction(card, token) {
    const btns = card.querySelector('.btns');
    btns.querySelectorAll('button').forEach((b) => (b.disabled = true));
    btns.querySelector('[data-go]').innerHTML = '<span class="zpai-typing" style="padding:0"><span></span><span></span><span></span></span>';
    setBusy(true);
    try {
      const res = await post(urls.confirm(token));
      const data = await res.json();
      if (data.ok) {
        card.classList.add('done');
        card.querySelector('.zpai-card-head span:last-child').textContent = 'Done';
        btns.outerHTML = `<div class="zpai-result">${md(data.message)}${data.link ? `<br><a href="${attr(data.link)}">${esc(data.link_label || 'Open')} <i class="bi bi-arrow-right"></i></a>` : ''}</div>`;
        if (window.ZPConfetti && /payroll|Added/.test(data.message)) ZPConfetti.burst({ count: 70 });
      } else {
        btns.outerHTML = `<div class="zpai-result" style="color:var(--ai-red)">${esc(data.message || 'That didn\'t work.')}</div>`;
      }
    } catch (e) {
      btns.outerHTML = '<div class="zpai-result" style="color:var(--ai-red)">Couldn\'t reach the server — nothing was changed.</div>';
    } finally {
      setBusy(false);
    }
  }

  function table(cols, rows) {
    const cell = (v) => (v && typeof v === 'object') ? (v.link ? `<a href="${attr(v.link)}">${esc(v.text ?? '')}</a>` : esc(v.text ?? '')) : esc(v ?? '—');
    return `<div class="zpai-tablewrap"><table class="zpai-table"><thead><tr>${(cols || []).map((c) => `<th>${esc(c)}</th>`).join('')}</tr></thead>`
      + `<tbody>${(rows || []).map((r) => `<tr>${r.map((v) => `<td>${cell(v)}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
  }

  /* ---------------- helpers ---------------- */
  function post(url) { return fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } }); }
  function div(cls) { const d = document.createElement('div'); d.className = cls; return d; }
  function esc(s) { return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
  function attr(s) { const u = String(s || ''); return /^(https?:)?\//.test(u) ? esc(u) : '#'; }
  // Tiny, safe markdown: **bold**, bullet lists, paragraphs.
  function md(text) {
    const lines = esc(text).split('\n');
    let html = '', list = false;
    lines.forEach((l) => {
      const b = l.match(/^\s*[-*•]\s+(.*)/);
      if (b) { if (!list) { html += '<ul>'; list = true; } html += `<li>${b[1]}</li>`; return; }
      if (list) { html += '</ul>'; list = false; }
      if (l.trim()) html += `<p>${l}</p>`;
    });
    if (list) html += '</ul>';
    return html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  }
})();
