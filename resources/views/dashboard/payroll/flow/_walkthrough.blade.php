{{--
    "How payroll works" — an animated, five-step walkthrough of the pay run journey.
    Open it with any element that has [data-walkthrough] (optionally ="3" to jump to a step).
    Opens by itself the first time someone visits the payroll home.
--}}
@once
@push('modals')
<div class="wt-bg" id="wt" role="dialog" aria-modal="true" aria-labelledby="wtTitle" aria-hidden="true">
    <div class="wt">
        <button type="button" class="wt-x" data-wt-close aria-label="Close"><i class="bi bi-x-lg"></i></button>

        <div class="wt-rail" aria-hidden="true">
            @foreach(['Calculate', 'Check', 'Approve', 'Lock', 'Pay & file'] as $i => $label)
                <button type="button" class="wt-rail-step" data-wt-go="{{ $i }}"><span>{{ $i + 1 }}</span>{{ $label }}</button>
            @endforeach
            <div class="wt-rail-bar"><i id="wtBar"></i></div>
        </div>

        <div class="wt-slides">
            {{-- 1 Calculate --}}
            <section class="wt-slide" data-step="0">
                <div class="wt-scene s-calc" aria-hidden="true">
                    <div class="sheet">
                        @foreach([['Mary B.', '8,250'], ['John P.', '6,100'], ['Chanda M.', '5,480']] as $j => [$n, $v])
                            <div class="wt-row" style="--d:{{ $j * .35 }}s"><span class="av"></span><b>{{ $n }}</b><span class="bars"><i></i><i></i><i></i></span><em>K {{ $v }}</em></div>
                        @endforeach
                    </div>
                    <div class="chips"><span style="--d:1.6s">PAYE</span><span style="--d:1.8s">NAPSA</span><span style="--d:2s">NHIMA</span><span style="--d:2.2s">Loans</span><span style="--d:2.4s">Overtime</span></div>
                </div>
                <div class="wt-copy">
                    <div class="wt-eyebrow">Step 1 · Calculate</div>
                    <h2 id="wtTitle">Pick the month and who's being paid</h2>
                    <p>Press <b>Calculate</b> and we work out everyone's gross pay, PAYE, NAPSA and NHIMA in one go, and pull in loan repayments and approved overtime.</p>
                    <div class="wt-why"><i class="bi bi-lightbulb"></i><span><b>Why:</b> no spreadsheets or manual tax tables — every payslip is built from the employee's profile and your pay rules.</span></div>
                    <p class="wt-small">Need a bonus, backpay or leavers' run in the same month? Start another pay run and pick just those people.</p>
                </div>
            </section>

            {{-- 2 Check --}}
            <section class="wt-slide" data-step="1">
                <div class="wt-scene s-check" aria-hidden="true">
                    <div class="sheet">
                        @foreach(range(1, 5) as $j)
                            <div class="wt-row {{ $j === 3 ? 'flag' : '' }}"><span class="av"></span><span class="bars"><i></i><i></i></span><em></em></div>
                        @endforeach
                    </div>
                    <div class="lens"><i class="bi bi-search"></i></div>
                    <div class="pop"><i class="bi bi-exclamation-triangle-fill"></i> Take-home up 38% vs last month</div>
                </div>
                <div class="wt-copy">
                    <div class="wt-eyebrow">Step 2 · Check</div>
                    <h2>Look at the highlights, not every line</h2>
                    <p>We flag what's unusual — big jumps, missing bank details or TPINs, people on unpaid leave. Fix it on the profile, add a one-off if needed, and recalculate as often as you like.</p>
                    <div class="wt-why"><i class="bi bi-lightbulb"></i><span><b>Why:</b> mistakes are cheapest to fix now, before anyone signs off or money moves.</span></div>
                </div>
            </section>

            {{-- 3 Approve --}}
            <section class="wt-slide" data-step="2">
                <div class="wt-scene s-approve" aria-hidden="true">
                    <div class="doc"><i class="bi bi-file-earmark-text"></i></div>
                    <div class="people">
                        @foreach(['HR', 'Finance', 'Director'] as $j => $who)
                            <div class="p" style="--d:{{ .6 + $j * .7 }}s"><span class="av">{{ substr($who, 0, 1) }}</span><small>{{ $who }}</small><i class="bi bi-check-circle-fill"></i></div>
                        @endforeach
                    </div>
                </div>
                <div class="wt-copy">
                    <div class="wt-eyebrow">Step 3 · Approve</div>
                    <h2>Send it through your approval chain</h2>
                    <p>Submit it and each approver gets an email, one after the other. They can approve, or send it back with a comment. While it's in approval the figures are frozen.</p>
                    <div class="wt-why"><i class="bi bi-lightbulb"></i><span><b>Why:</b> a second pair of eyes on every payroll — the person who prepared it can't approve it.</span></div>
                </div>
            </section>

            {{-- 4 Lock --}}
            <section class="wt-slide" data-step="3">
                <div class="wt-scene s-lock" aria-hidden="true">
                    <div class="padlock"><span class="shackle"></span><span class="body"><i class="bi bi-shield-check"></i></span></div>
                    <div class="rays"><span>Loan balances updated</span><span>Payslips on the portal</span><span>Figures frozen</span></div>
                </div>
                <div class="wt-copy">
                    <div class="wt-eyebrow">Step 4 · Lock</div>
                    <h2>The last approval locks it</h2>
                    <p>Locked means final: loan repayments are posted to the ledger and payslips appear on the employee portal (or go out by email — your choice).</p>
                    <div class="wt-why"><i class="bi bi-lightbulb"></i><span><b>Why:</b> a permanent record your staff, auditors and ZRA can rely on.</span></div>
                    <p class="wt-small"><i class="bi bi-unlock"></i> Spotted a mistake? An approver can <b>unlock</b> it with a reason — loans are reversed, it's re-approved, and everything is logged.</p>
                </div>
            </section>

            {{-- 5 Pay & file --}}
            <section class="wt-slide" data-step="4">
                <div class="wt-scene s-pay" aria-hidden="true">
                    <div class="bank"><i class="bi bi-bank"></i><small>Bank file</small></div>
                    <div class="coins">@foreach(range(0, 4) as $j)<i style="--d:{{ $j * .3 }}s"></i>@endforeach</div>
                    <div class="returns">
                        @foreach(['NAPSA', 'NHIMA', 'ZRA'] as $j => $r)
                            <div style="--d:{{ 1.4 + $j * .35 }}s"><i class="bi bi-file-earmark-check"></i>{{ $r }}</div>
                        @endforeach
                    </div>
                </div>
                <div class="wt-copy">
                    <div class="wt-eyebrow">Step 5 · Pay &amp; file</div>
                    <h2>Pay your people and file the returns</h2>
                    <p>Download the bank file, share payslips, then review the NAPSA, NHIMA and ZRA returns — correct anything — and export them. Mark the run as paid when the money's gone.</p>
                    <div class="wt-why"><i class="bi bi-lightbulb"></i><span><b>Why:</b> one place to finish the month: staff paid, government filed, nothing forgotten.</span></div>
                </div>
            </section>
        </div>

        <div class="wt-foot">
            <label class="wt-auto"><input type="checkbox" id="wtAuto"> Play automatically</label>
            <div class="wt-dots" id="wtDots"></div>
            <div class="wt-nav">
                <button type="button" class="pp-btn pp-btn-ghost pp-btn-sm" id="wtPrev"><i class="bi bi-arrow-left"></i> Back</button>
                <button type="button" class="pp-btn pp-btn-primary pp-btn-sm" id="wtNext">Next <i class="bi bi-arrow-right"></i></button>
            </div>
        </div>
    </div>
</div>

<style>
.wt-bg { position: fixed; inset: 0; z-index: 1070; background: rgba(5, 20, 12, .62); backdrop-filter: blur(3px); display: none; align-items: center; justify-content: center; padding: 16px; font-family: var(--zp-font); }
.wt-bg.open { display: flex; animation: wtIn .25s ease-out; }
.wt { position: relative; width: 100%; max-width: 860px; max-height: calc(100vh - 32px); overflow: auto; background: #fff; border-radius: 24px; box-shadow: 0 40px 100px rgba(0,0,0,.35); padding: 1.3rem 1.4rem 1.1rem; }
.wt-x { position: absolute; top: .8rem; right: .8rem; width: 36px; height: 36px; border-radius: 50%; border: 0; background: var(--zp-mist); cursor: pointer; z-index: 2; }
.wt-rail { position: relative; display: grid; grid-template-columns: repeat(5, 1fr); gap: .3rem; margin: 0 2.6rem 1.1rem 0; }
.wt-rail-step { border: 0; background: none; cursor: pointer; font: 600 .76rem var(--zp-font); color: var(--zp-ink-3); display: flex; align-items: center; gap: .4rem; padding: .3rem 0 .7rem; text-align: left; }
.wt-rail-step span { width: 22px; height: 22px; border-radius: 50%; display: grid; place-items: center; background: var(--zp-mist); font-size: .7rem; flex: none; transition: all .3s; }
.wt-rail-step.on { color: var(--zp-ink); }
.wt-rail-step.on span, .wt-rail-step.done span { background: var(--zp-green); color: #fff; }
.wt-rail-bar { position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: var(--zp-mist); border-radius: 3px; overflow: hidden; }
.wt-rail-bar i { display: block; height: 100%; width: 20%; background: var(--zp-green); transition: width .5s cubic-bezier(.3,.7,.2,1); }
.wt-slides { position: relative; min-height: 330px; }
.wt-slide { display: none; grid-template-columns: 1fr 1fr; gap: 1.6rem; align-items: center; }
.wt-slide.on { display: grid; animation: wtSlide .45s cubic-bezier(.3,.7,.2,1); }
.wt-copy h2 { font: 700 1.45rem/1.2 var(--zp-font-display); margin: .25rem 0 .6rem; color: var(--zp-ink); }
.wt-copy p { color: var(--zp-ink-2); font-size: .93rem; line-height: 1.55; margin: 0 0 .8rem; }
.wt-eyebrow { font-size: .72rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--zp-green); }
.wt-why { display: flex; gap: .6rem; background: var(--zp-green-50); color: var(--zp-forest); border-radius: 14px; padding: .7rem .85rem; font-size: .86rem; margin-bottom: .7rem; }
.wt-why i { color: var(--zp-green); }
.wt-small { font-size: .82rem !important; color: var(--zp-ink-3) !important; }
.wt-foot { display: flex; align-items: center; gap: 1rem; margin-top: 1.1rem; padding-top: .9rem; border-top: 1px solid var(--zp-line); }
.wt-auto { font-size: .8rem; color: var(--zp-ink-3); display: flex; gap: .4rem; align-items: center; cursor: pointer; }
.wt-auto input { accent-color: var(--zp-green); }
.wt-dots { flex: 1; display: flex; justify-content: center; gap: .35rem; }
.wt-dots i { width: 7px; height: 7px; border-radius: 99px; background: var(--zp-line); transition: all .3s; }
.wt-dots i.on { width: 22px; background: var(--zp-green); }
.wt-nav { display: flex; gap: .4rem; }

/* scenes */
.wt-scene { position: relative; height: 290px; border-radius: 20px; overflow: hidden; background: radial-gradient(120% 120% at 100% 0%, #11603a 0%, var(--zp-forest, #0a3b22) 55%, #06261a 100%); color: #fff; }
.wt-scene .sheet { position: absolute; left: 22px; right: 22px; top: 24px; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.14); border-radius: 14px; padding: 10px; }
.wt-scene .wt-row { display: flex; align-items: center; gap: 8px; padding: 8px; border-radius: 9px; font-size: .78rem; }
.wt-scene .wt-row + .row { margin-top: 4px; }
.wt-scene .av { width: 24px; height: 24px; border-radius: 50%; background: rgba(255,255,255,.25); flex: none; }
.wt-scene .wt-row b { width: 78px; font-weight: 600; white-space: nowrap; }
.wt-scene .bars { flex: 1; display: flex; gap: 5px; }
.wt-scene .bars i { height: 7px; border-radius: 4px; background: rgba(255,255,255,.22); flex: 1; }
.wt-scene .wt-row em { font-style: normal; font-weight: 700; min-width: 64px; text-align: right; }

.on .s-calc .wt-row { opacity: 0; animation: wtRow .5s ease-out forwards; animation-delay: var(--d); }
.on .s-calc .wt-row em { opacity: 0; animation: wtFade .4s ease-out forwards; animation-delay: calc(var(--d) + .45s); }
.on .s-calc .bars i { transform-origin: left; transform: scaleX(0); animation: wtGrow .6s ease-out forwards; animation-delay: calc(var(--d) + .15s); background: #8fd3a6; }
.s-calc .chips { position: absolute; left: 22px; right: 22px; bottom: 22px; display: flex; flex-wrap: wrap; gap: 6px; }
.s-calc .chips span { font-size: .72rem; font-weight: 700; padding: 5px 10px; border-radius: 99px; background: #fff; color: var(--zp-forest, #0a3b22); opacity: 0; }
.on .s-calc .chips span { animation: wtPop .4s cubic-bezier(.3,1.6,.5,1) forwards; animation-delay: var(--d); }

.s-check .wt-row.flag { background: rgba(240, 166, 80, .0); }
.on .s-check .wt-row.flag { animation: wtFlag .5s ease-out 1.6s forwards; }
.s-check .lens { position: absolute; top: 30px; left: 30px; width: 58px; height: 58px; border-radius: 50%; border: 3px solid #fff; display: grid; place-items: center; font-size: 1.2rem; background: rgba(255,255,255,.12); }
.on .s-check .lens { animation: wtSweep 2.6s ease-in-out forwards; }
.s-check .pop { position: absolute; left: 50%; bottom: 22px; transform: translateX(-50%) translateY(10px); background: #fff; color: #7a4611; font-size: .78rem; font-weight: 700; padding: 8px 12px; border-radius: 12px; white-space: nowrap; opacity: 0; }
.s-check .pop i { color: #e08b2c; }
.on .s-check .pop { animation: wtUp .45s ease-out 1.9s forwards; }

.s-approve .doc { position: absolute; left: 50%; top: 28px; transform: translateX(-50%); width: 64px; height: 76px; border-radius: 12px; background: #fff; color: var(--zp-forest, #0a3b22); display: grid; place-items: center; font-size: 2rem; }
.on .s-approve .doc { animation: wtFloat 2.4s ease-in-out infinite; }
.s-approve .people { position: absolute; left: 18px; right: 18px; bottom: 34px; display: flex; justify-content: space-around; }
.s-approve .p { position: relative; display: flex; flex-direction: column; align-items: center; gap: 6px; font-size: .74rem; }
.s-approve .p .av { width: 52px; height: 52px; display: grid; place-items: center; font-weight: 800; font-size: 1.05rem; background: rgba(255,255,255,.16); border: 2px solid rgba(255,255,255,.3); }
.s-approve .p > i { position: absolute; top: -6px; right: -4px; color: #8fd3a6; font-size: 1.25rem; background: var(--zp-forest, #0a3b22); border-radius: 50%; transform: scale(0); }
.on .s-approve .p > i { animation: wtPop .45s cubic-bezier(.3,1.6,.5,1) forwards; animation-delay: var(--d); }
.on .s-approve .p .av { animation: wtGlow .5s ease-out forwards; animation-delay: var(--d); }
.s-approve .people::before { content: ""; position: absolute; left: 12%; right: 12%; top: 26px; height: 2px; background: rgba(255,255,255,.18); z-index: -1; }

.s-lock .padlock { position: absolute; left: 50%; top: 46px; transform: translateX(-50%); width: 110px; height: 130px; }
.s-lock .shackle { position: absolute; left: 22px; top: 0; width: 66px; height: 70px; border: 11px solid #fff; border-bottom: 0; border-radius: 40px 40px 0 0; transform: translateY(-22px); }
.on .s-lock .shackle { animation: wtShut .5s cubic-bezier(.5,0,.4,1.4) .7s forwards; }
.s-lock .body { position: absolute; left: 0; bottom: 0; width: 110px; height: 80px; border-radius: 18px; background: #fff; color: var(--zp-green, #138a4b); display: grid; place-items: center; font-size: 2rem; }
.on .s-lock .body { animation: wtThud .35s ease-out 1.15s; }
.s-lock .rays { position: absolute; left: 0; right: 0; bottom: 18px; display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; padding: 0 14px; }
.s-lock .rays span { font-size: .72rem; font-weight: 700; padding: 5px 10px; border-radius: 99px; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.25); opacity: 0; }
.on .s-lock .rays span { animation: wtUpPlain .4s ease-out forwards; }
.on .s-lock .rays span:nth-child(1) { animation-delay: 1.4s; } .on .s-lock .rays span:nth-child(2) { animation-delay: 1.65s; } .on .s-lock .rays span:nth-child(3) { animation-delay: 1.9s; }

.s-pay .bank { position: absolute; right: 30px; top: 34px; width: 86px; height: 86px; border-radius: 20px; background: #fff; color: var(--zp-forest, #0a3b22); display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 1.9rem; }
.s-pay .bank small { font-size: .66rem; font-weight: 700; }
.s-pay .coins i { position: absolute; left: 34px; top: 66px; width: 18px; height: 18px; border-radius: 50%; background: #f2c14e; box-shadow: inset 0 -3px 0 #c9962a; opacity: 0; }
.on .s-pay .coins i { animation: wtCoin 1.4s ease-in-out infinite; animation-delay: var(--d); }
.s-pay .returns { position: absolute; left: 20px; right: 20px; bottom: 22px; display: flex; gap: 8px; justify-content: center; }
.s-pay .returns div { display: flex; align-items: center; gap: 6px; background: #fff; color: var(--zp-forest, #0a3b22); font-size: .78rem; font-weight: 800; padding: 8px 12px; border-radius: 12px; opacity: 0; }
.s-pay .returns i { color: var(--zp-green, #138a4b); }
.on .s-pay .returns div { animation: wtStamp .45s cubic-bezier(.3,1.6,.5,1) forwards; animation-delay: var(--d); }

@keyframes wtIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes wtSlide { from { opacity: 0; transform: translateX(24px); } to { opacity: 1; transform: none; } }
@keyframes wtRow { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
@keyframes wtFade { to { opacity: 1; } }
@keyframes wtGrow { to { transform: scaleX(1); } }
@keyframes wtPop { from { opacity: 0; transform: scale(.4); } to { opacity: 1; transform: scale(1); } }
@keyframes wtUp { to { opacity: 1; transform: translateX(-50%) translateY(0); } }
.s-lock .rays span { transform: translateY(8px); }
@keyframes wtSweep { 0% { transform: translate(0, 0); } 35% { transform: translate(230px, 40px); } 65% { transform: translate(40px, 92px); } 100% { transform: translate(150px, 92px); } }
@keyframes wtFlag { to { background: rgba(240, 166, 80, .35); box-shadow: inset 3px 0 0 #f0a650; } }
@keyframes wtFloat { 0%, 100% { transform: translateX(-50%) translateY(0); } 50% { transform: translateX(-50%) translateY(-6px); } }
@keyframes wtGlow { to { background: rgba(143, 211, 166, .3); border-color: #8fd3a6; } }
@keyframes wtShut { to { transform: translateY(0); } }
@keyframes wtThud { 50% { transform: scale(1.06); } }
@keyframes wtCoin { 0% { opacity: 0; transform: translate(0, 0); } 15% { opacity: 1; } 80% { opacity: 1; } 100% { opacity: 0; transform: translate(240px, -10px); } }
@keyframes wtStamp { from { opacity: 0; transform: scale(1.6) rotate(-8deg); } to { opacity: 1; transform: none; } }
@keyframes wtUpPlain { to { opacity: 1; transform: translateY(0); } }

@media (max-width: 720px) {
    .wt-slide { grid-template-columns: 1fr; gap: 1rem; }
    .wt-scene { height: 230px; }
    .wt-rail-step { font-size: 0; gap: 0; justify-content: center; }
    .wt-rail { margin-right: 2.4rem; }
    .wt-auto { display: none; }
    .s-check .lens { animation: none !important; }
}
@media (prefers-reduced-motion: reduce) {
    .wt-bg *, .wt-bg *::before { animation-duration: .01s !important; animation-delay: 0s !important; animation-iteration-count: 1 !important; }
}
</style>

<script>
(function () {
    const bg = document.getElementById('wt');
    if (! bg) return;
    const slides = [...bg.querySelectorAll('.wt-slide')];
    const rail = [...bg.querySelectorAll('.wt-rail-step')];
    const dots = document.getElementById('wtDots');
    const prev = document.getElementById('wtPrev'), next = document.getElementById('wtNext'), auto = document.getElementById('wtAuto');
    let i = 0, timer = null, lastFocus = null;
    slides.forEach(() => dots.appendChild(document.createElement('i')));

    function show(n) {
        i = Math.max(0, Math.min(slides.length - 1, n));
        slides.forEach((s, k) => s.classList.toggle('on', k === i));
        rail.forEach((r, k) => { r.classList.toggle('on', k === i); r.classList.toggle('done', k < i); });
        [...dots.children].forEach((d, k) => d.classList.toggle('on', k === i));
        document.getElementById('wtBar').style.width = ((i + 1) / slides.length * 100) + '%';
        prev.style.visibility = i ? 'visible' : 'hidden';
        next.innerHTML = i === slides.length - 1 ? 'Got it <i class="bi bi-check-lg"></i>' : 'Next <i class="bi bi-arrow-right"></i>';
        schedule();
    }
    function schedule() {
        clearTimeout(timer);
        if (auto.checked && bg.classList.contains('open') && i < slides.length - 1) timer = setTimeout(() => show(i + 1), 6500);
    }
    function open(n) {
        lastFocus = document.activeElement;
        bg.classList.add('open'); bg.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        show(n || 0); next.focus();
        try { localStorage.setItem('zpPayrollTourSeen', '1'); } catch (e) {}
    }
    function close() {
        bg.classList.remove('open'); bg.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = ''; clearTimeout(timer);
        if (lastFocus) lastFocus.focus();
    }
    prev.onclick = () => show(i - 1);
    next.onclick = () => i === slides.length - 1 ? close() : show(i + 1);
    auto.onchange = schedule;
    rail.forEach((r, k) => r.onclick = () => show(k));
    bg.addEventListener('click', e => { if (e.target === bg || e.target.closest('[data-wt-close]')) close(); });
    document.addEventListener('keydown', e => {
        if (! bg.classList.contains('open')) return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowRight') show(i + 1);
        if (e.key === 'ArrowLeft') show(i - 1);
    });
    document.addEventListener('click', e => {
        const t = e.target.closest('[data-walkthrough]');
        if (t) { e.preventDefault(); open(parseInt(t.dataset.walkthrough || '0', 10) || 0); }
    });

    // First visit to the payroll home: show it once.
    let seen = '1';
    try { seen = localStorage.getItem('zpPayrollTourSeen'); } catch (e) {}
    if (! seen && document.querySelector('[data-walkthrough-auto]')) setTimeout(() => open(0), 600);
})();
</script>
@endpush
@endonce
