/* ZamPayroll — People module helpers (confetti, money, copy) */
window.zpPeople = (function () {
  const colors = ['#00742D', '#C42926', '#101512', '#C8742B', '#3FB06A'];

  function confetti(count = 90) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const box = document.createElement('div');
    box.className = 'pp-confetti';
    for (let i = 0; i < count; i++) {
      const p = document.createElement('i');
      p.style.left = Math.random() * 100 + 'vw';
      p.style.background = colors[i % colors.length];
      p.style.setProperty('--dx', (Math.random() * 200 - 100) + 'px');
      p.style.setProperty('--r', (Math.random() * 900 - 450) + 'deg');
      p.style.animationDuration = (1.6 + Math.random() * 1.6) + 's';
      p.style.animationDelay = (Math.random() * .35) + 's';
      box.appendChild(p);
    }
    document.body.appendChild(box);
    setTimeout(() => box.remove(), 3800);
  }

  function money(v) {
    const n = parseFloat(String(v ?? '').replace(/[^\d.]/g, ''));
    return isNaN(n) ? '' : 'K ' + n.toLocaleString('en-ZM', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-copy]');
    if (!b) return;
    navigator.clipboard?.writeText(b.dataset.copy).then(() => {
      const old = b.innerHTML;
      b.innerHTML = '<i class="bi bi-check2"></i>';
      setTimeout(() => (b.innerHTML = old), 1200);
    });
  });

  return { confetti, money, esc };
})();
