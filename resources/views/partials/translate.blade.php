{{--
    Language switcher powered by Google Translate's website widget.
    Usage: @include('partials.translate', ['variant' => 'light'|'dark'|'compact'])
    Text is only sent to Google once someone picks a language other than English.
--}}
@php $tLangs = config('languages'); $tVariant = $variant ?? 'light'; @endphp
<div class="zp-lang zp-lang-{{ $tVariant }} notranslate" translate="no" data-zp-lang>
    <button type="button" class="zp-lang-btn" aria-haspopup="listbox" aria-expanded="false" aria-label="Change language">
        <i class="bi bi-translate"></i><span class="zp-lang-current">English</span><i class="bi bi-chevron-down zp-lang-caret"></i>
    </button>
    <ul class="zp-lang-menu" role="listbox" hidden>
        @foreach($tLangs as $code => $l)
            <li><button type="button" role="option" data-lang="{{ $code }}" lang="{{ $code }}"><span>{{ $l['native'] }}</span><small>{{ $l['label'] }}</small></button></li>
        @endforeach
        <li class="zp-lang-note">Automatic translation by Google</li>
    </ul>
</div>

@once
<div id="google_translate_element" style="display:none"></div>
<style>
.zp-lang { position: relative; font-family: 'Inter', 'Segoe UI', Arial, sans-serif; }
.zp-lang-btn { display: inline-flex; align-items: center; gap: .45rem; border-radius: 10px; padding: .45rem .7rem; font-size: .85rem; font-weight: 600; cursor: pointer; border: 1px solid #DCE5DE; background: #fff; color: #45524A; }
.zp-lang-dark .zp-lang-btn { background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.18); color: #E6F2EA; }
.zp-lang-compact .zp-lang-current { display: none; }
.zp-lang-caret { font-size: .7rem; opacity: .7; }
.zp-lang-menu { position: absolute; right: 0; top: calc(100% + 6px); z-index: 2000; list-style: none; margin: 0; padding: .35rem; min-width: 210px; max-height: 360px; overflow: auto; background: #fff; border: 1px solid #DCE5DE; border-radius: 14px; box-shadow: 0 20px 40px -20px rgba(10,30,20,.35); }
.zp-lang-up .zp-lang-menu { top: auto; bottom: calc(100% + 6px); }
.zp-lang-menu button { width: 100%; display: flex; justify-content: space-between; align-items: baseline; gap: .8rem; border: 0; background: none; padding: .5rem .65rem; border-radius: 9px; font-size: .9rem; color: #15201A; cursor: pointer; text-align: left; }
.zp-lang-menu button:hover, .zp-lang-menu button[aria-selected="true"] { background: #E8F3EC; }
.zp-lang-menu small { color: #7B877F; font-size: .75rem; }
.zp-lang-note { font-size: .7rem; color: #7B877F; padding: .4rem .65rem .2rem; border-top: 1px solid #EEF2EF; margin-top: .25rem; }
/* Hide Google's own banner/tooltip chrome; keep the page from jumping down. */
body { top: 0 !important; }
iframe.skiptranslate, .goog-te-banner-frame, #goog-gt-tt, .goog-te-balloon-frame, .VIpgJd-ZVi9od-ORHb-OEVmcd, .VIpgJd-ZVi9od-aZ2wEe-wOHMyf { display: none !important; visibility: hidden !important; }
.goog-text-highlight { background: none !important; box-shadow: none !important; }
font[style] { background: none !important; box-shadow: none !important; }
</style>
<script>
(function () {
    const langs = @json(collect(config('languages'))->map(fn ($l) => $l['native']));
    const host = location.hostname;
    const current = (decodeURIComponent(document.cookie).match(/(?:^|;\s*)googtrans=\/en\/([^;]+)/) || [])[1] || 'en';
    let loaded = false;

    function setCookie(val) {
        const exp = val ? '' : '; expires=Thu, 01 Jan 1970 00:00:00 GMT';
        const v = val ? '/en/' + val : '';
        document.cookie = 'googtrans=' + v + '; path=/' + exp;
        // also on the parent domain so sub-domain workspaces share the choice
        const parts = host.split('.');
        if (parts.length > 1) document.cookie = 'googtrans=' + v + '; path=/; domain=.' + parts.slice(-2).join('.') + exp;
    }

    function loadGoogle() {
        if (loaded) return; loaded = true;
        window.googleTranslateElementInit = function () {
            new google.translate.TranslateElement({ pageLanguage: 'en', includedLanguages: Object.keys(langs).join(','), autoDisplay: false }, 'google_translate_element');
        };
        const s = document.createElement('script');
        s.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
        s.async = true;
        document.head.appendChild(s);
    }

    // Only contact Google when a non-English language is active.
    if (current !== 'en') loadGoogle();

    function init() { document.querySelectorAll('[data-zp-lang]').forEach(function (box) {
        const btn = box.querySelector('.zp-lang-btn'), menu = box.querySelector('.zp-lang-menu');
        box.querySelector('.zp-lang-current').textContent = langs[current] || 'English';
        box.querySelectorAll('[data-lang]').forEach(function (b) { if (b.dataset.lang === current) b.setAttribute('aria-selected', 'true'); });
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const open = menu.hidden;
            document.querySelectorAll('.zp-lang-menu').forEach(m => m.hidden = true);
            menu.hidden = !open; btn.setAttribute('aria-expanded', String(open));
        });
        menu.addEventListener('click', function (e) {
            const b = e.target.closest('[data-lang]');
            if (!b) return;
            setCookie(b.dataset.lang === 'en' ? '' : b.dataset.lang);
            try { localStorage.setItem('zp_lang', b.dataset.lang); } catch (err) {}
            location.reload();
        });
    }); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    document.addEventListener('click', function () { document.querySelectorAll('.zp-lang-menu').forEach(m => m.hidden = true); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') document.querySelectorAll('.zp-lang-menu').forEach(m => m.hidden = true); });
})();
</script>
@endonce
