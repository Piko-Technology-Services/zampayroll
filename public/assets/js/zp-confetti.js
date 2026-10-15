/* Tiny flag-coloured confetti burst. ZPConfetti.burst() — respects reduced motion. */
(function () {
  window.ZPConfetti = {
    burst: function (opts) {
      if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      opts = opts || {};
      var colors = ['#00742D', '#C42926', '#101512', '#C8742B', '#7FD69E'];
      var canvas = document.createElement('canvas');
      canvas.className = 'zp-confetti';
      document.body.appendChild(canvas);
      var ctx = canvas.getContext('2d');
      var W = canvas.width = window.innerWidth, H = canvas.height = window.innerHeight;
      var pieces = [];
      var count = opts.count || 160;
      for (var i = 0; i < count; i++) {
        pieces.push({
          x: W / 2 + (Math.random() - .5) * 120, y: H * .35,
          vx: (Math.random() - .5) * 16, vy: -Math.random() * 14 - 4,
          w: 6 + Math.random() * 6, h: 8 + Math.random() * 8,
          r: Math.random() * Math.PI, vr: (Math.random() - .5) * .3,
          c: colors[i % colors.length]
        });
      }
      var start = performance.now();
      (function frame(t) {
        ctx.clearRect(0, 0, W, H);
        pieces.forEach(function (p) {
          p.vy += .35; p.vx *= .99; p.x += p.vx; p.y += p.vy; p.r += p.vr;
          ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r);
          ctx.fillStyle = p.c; ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h); ctx.restore();
        });
        if (t - start < 3200) requestAnimationFrame(frame); else canvas.remove();
      })(start);
    }
  };
})();
