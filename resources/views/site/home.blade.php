@php
    $faqs = [
        ['Is ZamPayroll really free for small teams?', 'Yes. Companies with up to 10 employees use the full system free on our shared cloud — payroll, payslips, leave, attendance, overtime, loans, the employee portal and AI mode. Your data stays private to your company.'],
        ['Does it calculate PAYE, NAPSA and NHIMA correctly?', 'ZamPayroll applies the ZRA PAYE bands, NAPSA (with its ceiling) and NHIMA automatically on every payslip, and produces the returns and bank payment files you need each month. Rates are kept up to date when the national budget changes them.'],
        ['We already use Excel or another payroll system. Is it hard to move?', 'No. Upload your employee list as Excel or CSV — ZamPayroll recognises common column names from other systems — or paste it into AI mode. Anything missing (like an NRC or NAPSA number) can be completed later.'],
        ['Can my staff see their own payslips?', 'Yes. With one click HR creates a self-service portal for each employee. They sign in on their phone to download payslips and apply for leave, overtime and loans.'],
        ['Can different people in our company have different access?', 'Yes. Everyone signs in with their own email and password. Your administrator decides what each role can do — for example a manager can approve leave but not see salaries.'],
        ['Does it work in other languages?', 'Yes. The whole system can be shown in Chinese, French, Portuguese, Somali, Arabic, Hindi, Gujarati, Urdu, Swahili, Nyanja and more using automatic translation.'],
        ['What happens when we grow beyond 10 employees?', 'Larger companies get a dedicated workspace with their own web address (yourcompany.zampayroll.com) and their own database. Talk to us for pricing.'],
    ];

    $seo = [
        'title' => 'ZamPayroll — Payroll & HR software for Zambia (ZRA PAYE, NAPSA, NHIMA)',
        'description' => 'Run payroll in minutes with ZamPayroll: PAYE, NAPSA and NHIMA calculated automatically, payslips on every phone, leave, attendance and loans in one place, and an AI assistant. Free for up to 10 employees.',
        'path' => '/',
        'schema' => [
            [
                '@context' => 'https://schema.org',
                '@type' => 'SoftwareApplication',
                'name' => 'ZamPayroll',
                'applicationCategory' => 'BusinessApplication',
                'applicationSubCategory' => 'Payroll and HR software',
                'operatingSystem' => 'Web, Android, iOS (browser)',
                'url' => config('site.url'),
                'image' => config('site.og_image'),
                'description' => config('site.description'),
                'areaServed' => ['@type' => 'Country', 'name' => 'Zambia'],
                'offers' => [
                    ['@type' => 'Offer', 'name' => 'Free — up to 10 employees', 'price' => '0', 'priceCurrency' => 'ZMW', 'url' => route('register')],
                ],
                'featureList' => 'ZRA PAYE, NAPSA and NHIMA calculations; payslips; employee self-service portal; leave management; attendance; overtime; staff loans; bank payment files; AI assistant; multilingual',
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faqs),
            ],
        ],
    ];
@endphp
@extends('layouts.site')

@section('content')

{{-- =========================== HERO =========================== --}}
<section class="hero">
    <div class="wrap hero-grid">
        <div>
            <span class="hero-badge"><i>★</i> Free for teams of up to 10 employees</span>
            <h1>Zambian payroll, done in minutes — not days.</h1>
            <p class="hero-sub">PAYE, NAPSA and NHIMA calculated for you. Payslips on every employee's phone. Leave, attendance, overtime and loans flowing straight into payroll. And an assistant you can simply ask.</p>
            <div class="hero-ctas">
                <a class="btn btn-primary" href="{{ route('register') }}"><i class="bi bi-rocket-takeoff"></i> Start free</a>
                <a class="btn btn-line" href="#demo"><i class="bi bi-calendar-check"></i> Book a demo</a>
                @if(config('site.demo_video'))
                    <button class="btn btn-line" type="button" onclick="document.getElementById('vmodal').hidden=false;document.getElementById('vframe').src=@json(config('site.demo_video'))"><i class="bi bi-play-circle"></i> Watch</button>
                @endif
            </div>
            <div class="hero-fine"><span>No card needed</span><span>Set up in about 3 minutes</span><span>Made in Zambia</span></div>
        </div>

        {{-- AI mode demo: plays once when it scrolls into view --}}
        <div class="demo" id="aiDemo" aria-label="Example of ZamPayroll AI mode">
            <div class="demo-flag"></div>
            <div class="demo-head">
                <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="#00742D" d="M12 1.8c.5 4.9 2.3 7.6 7.2 8.6.6.1.6.9 0 1-4.9 1-6.7 3.7-7.2 8.6-.1.6-.9.6-1 0-.5-4.9-2.3-7.6-7.2-8.6-.6-.1-.6-.9 0-1 4.9-1 6.7-3.7 7.2-8.6.1-.6.9-.6 1 0z"/><circle cx="19.5" cy="4.5" r="1.6" fill="#C8742B"/></svg>
                ZamPayroll AI <small>Kafue Agro Ltd</small>
            </div>
            <div class="demo-body">
                <div class="d-user step">Who is on leave today?</div>
                <div class="d-bot step">
                    <span class="av">✦</span>
                    <div style="flex:1">Two people are on leave today.
                        <div class="d-card">
                            <div class="d-card-h"><span>On leave today</span><span>Back</span></div>
                            <div class="d-row"><span>Chanda Mwansa — Annual</span><span>Mon 27 Oct</span></div>
                            <div class="d-row"><span>Mutale Zulu — Sick</span><span>Thu 23 Oct</span></div>
                        </div>
                    </div>
                </div>
                <div class="d-user step">Prepare October payroll</div>
                <div class="d-bot step">
                    <span class="av">✦</span>
                    <div style="flex:1">Ready when you are — nothing changes until you confirm.
                        <div class="d-card action">
                            <div class="d-card-h"><span>⚡ Prepare October 2026 payroll</span><span>Needs your OK</span></div>
                            <div class="d-row"><span>Active employees</span><span>38</span></div>
                            <div class="d-btns" id="demoBtns"><span class="ok">✓ Confirm</span><span class="no">Cancel</span></div>
                        </div>
                    </div>
                </div>
                <div class="d-bot step">
                    <span class="av">✦</span>
                    <div style="flex:1">Done — October is ready for review.
                        <div class="d-card">
                            <div class="d-stats">
                                <div><small>Net pay</small><b>K412,860</b></div>
                                <div><small>PAYE</small><b>K96,420</b></div>
                                <div><small>NAPSA</small><b>K24,180</b></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="demo-compose"><span>Ask or tell me what to do…</span><i>↑</i></div>
        </div>
    </div>
</section>

{{-- =========================== COMPLIANCE STRIP =========================== --}}
<section class="strip" aria-label="Built for Zambian compliance">
    <div class="wrap strip-in">
        <span>Built for Zambian compliance</span><span class="sep"></span>
        <strong>ZRA PAYE</strong><strong>NAPSA</strong><strong>NHIMA</strong><strong>WCFCB</strong>
        <span class="sep"></span>
        <span>Bank files for Zanaco, Absa, Stanbic, FNB, Access and more</span>
    </div>
</section>

{{-- =========================== THE MONTH =========================== --}}
<section class="sec">
    <div class="wrap">
        <div class="sec-head">
            <span class="kicker">How a month works</span>
            <h2>Your whole payroll month, in four calm steps.</h2>
            <p>Set your team up once. After that, every month is the same short routine — and everything employees request flows into payroll by itself.</p>
        </div>
        <div class="flow">
            <div class="flow-step"><div class="n">1</div><h3>Bring your team</h3><p>Upload your Excel list or paste it into AI mode. Missing details can wait.</p><span class="when">Once</span></div>
            <div class="flow-step"><div class="n">2</div><h3>Approve requests</h3><p>Leave, overtime and loans arrive from employees' phones. Approve in one tap.</p><span class="when">During the month</span></div>
            <div class="flow-step"><div class="n">3</div><h3>Prepare payroll</h3><p>PAYE, NAPSA, NHIMA, overtime and loan deductions are worked out for every employee.</p><span class="when">Around payday</span></div>
            <div class="flow-step"><div class="n">4</div><h3>Pay &amp; file</h3><p>Download the bank file and statutory returns. Payslips land on every phone.</p><span class="when">Same day</span></div>
        </div>
    </div>
</section>

{{-- =========================== MODULES =========================== --}}
<section class="sec sec-white" id="features">
    <div class="wrap">
        <div class="sec-head">
            <span class="kicker">Everything HR does, connected</span>
            <h2>One place for people, time and pay.</h2>
            <p>No more re-typing between spreadsheets. Each part feeds the next.</p>
        </div>
        <div class="mods">
            <div class="mod wide">
                <div>
                    <i class="bi bi-cash-stack ic"></i>
                    <h3>Payroll that knows Zambia</h3>
                    <p>Agree salaries the way Zambian employers do — from take-home pay — and let ZamPayroll work out gross, PAYE, NAPSA and NHIMA.</p>
                </div>
                <ul>
                    <li>Allowances, deductions and once-off adjustments</li>
                    <li>Loan repayments deducted automatically</li>
                    <li>Review, then finalise and lock the run</li>
                    <li>Statutory returns and bank payment files</li>
                </ul>
            </div>
            <div class="mod">
                <i class="bi bi-people ic"></i>
                <h3>People</h3>
                <p>One record per employee: contract, salary, NRC, TPIN, NAPSA, NHIMA, bank and documents.</p>
            </div>
            <div class="mod">
                <i class="bi bi-calendar2-heart ic"></i>
                <h3>Leave</h3>
                <p>Working days and return dates are counted for you, holidays included. Balances update on approval.</p>
            </div>
            <div class="mod">
                <i class="bi bi-clock-history ic"></i>
                <h3>Attendance</h3>
                <p>Mark the month on a calendar or import a file. Set working days and public holidays once.</p>
            </div>
            <div class="mod">
                <i class="bi bi-alarm ic"></i>
                <h3>Overtime &amp; loans</h3>
                <p>Overtime at 1.5× or 2×, priced before it's sent. Loans with a running ledger.</p>
            </div>
        </div>
        <p style="margin-top:1.6rem"><a href="{{ route('site.features') }}" class="btn btn-line">See every feature <i class="bi bi-arrow-right"></i></a></p>
    </div>
</section>

{{-- =========================== AI MODE =========================== --}}
<section class="sec ai-band">
    <div class="wrap">
        <div class="sec-head">
            <span class="kicker">AI mode</span>
            <h2>Just ask. Or tell it what to do.</h2>
            <p>A floating assistant on every screen that understands your company's people and payroll — and always shows you what it's about to change before anything happens.</p>
        </div>
        <div class="ai-grid">
            <div class="ai-col">
                <h3>Ask</h3>
                <div class="ai-q">
                    <div>“How many employees do we have?”</div>
                    <div>“Who is on leave today?”</div>
                    <div>“How much was our payroll last month, with PAYE and NAPSA?”</div>
                </div>
            </div>
            <div class="ai-col">
                <h3>Do</h3>
                <div class="ai-q">
                    <div class="do">“Add these employees” — paste a list or attach Excel</div>
                    <div class="do">“Approve leave for Mary”</div>
                    <div class="do">“Prepare this month's payroll”</div>
                </div>
            </div>
        </div>
        <div class="ai-trust">
            <div><b>You stay in charge</b><span>Every change waits for your Confirm.</span></div>
            <div><b>Respects roles</b><span>It can only do what your role allows.</span></div>
            <div><b>Private by design</b><span>Runs on ZamPayroll's servers — your data isn't sent to outside AI services.</span></div>
        </div>
        <p style="margin-top:2rem;position:relative"><a href="{{ route('site.ai') }}" class="btn btn-copper">How AI mode works <i class="bi bi-arrow-right"></i></a></p>
    </div>
</section>

{{-- =========================== SELF-SERVICE =========================== --}}
<section class="sec" id="portal">
    <div class="wrap split">
        <div>
            <span class="kicker">Employee self-service</span>
            <h2>Fewer “please send my payslip” messages.</h2>
            <p class="lead">With one click, each employee gets their own portal and an email with sign-in instructions. It works on any phone — no app to install.</p>
            <ul class="ticks">
                <li><i class="bi bi-check-circle-fill"></i> Every payslip, ready to view or download as PDF</li>
                <li><i class="bi bi-check-circle-fill"></i> Apply for leave, overtime and loans in a minute</li>
                <li><i class="bi bi-check-circle-fill"></i> See leave balance and loan repayments</li>
                <li><i class="bi bi-check-circle-fill"></i> Keep contact and next-of-kin details current</li>
            </ul>
            <a href="{{ route('register') }}" class="btn btn-primary">Give your team a portal</a>
        </div>
        <div>
            <div class="phone" aria-hidden="true">
                <div class="phone-in">
                    <div class="ph-top"><small>Good morning,</small><b>Chanda</b></div>
                    <div class="ph-slip"><small>October 2026 · Take-home pay</small><b>K14,269.60</b></div>
                    <div class="ph-acts">
                        <div><i class="bi bi-calendar2-heart"></i>Leave</div>
                        <div><i class="bi bi-alarm"></i>Overtime</div>
                        <div><i class="bi bi-cash-coin"></i>Loan</div>
                    </div>
                    <div class="ph-list">
                        <div><span>Annual leave · 5 days</span><span class="st">Approved</span></div>
                        <div><span>Overtime · 3 hrs</span><span class="st" style="color:#9A5418">Pending</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- =========================== TEAMS, ROLES & LANGUAGES =========================== --}}
<section class="sec sec-white">
    <div class="wrap split">
        <div>
            <div class="login-mock" aria-hidden="true">
                <b style="font-family:var(--s-display);font-size:1.3rem">Sign in</b>
                <label>Email</label><div class="f">payroll@kafueagro.co.zm</div>
                <label>Password</label><div class="f">••••••••••</div>
                <div class="roles-mini"><span>Administrator</span><span>HR Officer</span><span>Payroll Officer</span><span>Manager</span><span>Viewer</span><span>+ your own</span></div>
            </div>
        </div>
        <div>
            <span class="kicker">Your team, your rules — in your language</span>
            <h2>Everyone gets exactly the access they need.</h2>
            <p class="lead">Your team signs in with just their email and password. The administrator decides what each role can do — a manager approves leave without seeing salaries; an auditor gets read-only access.</p>
            <p class="lead" style="margin-bottom:.4rem">And the whole system speaks your team's language:</p>
            <div class="langs" translate="no">
                <span>English</span><span>中文</span><span>Français</span><span>Português</span><span>Soomaali</span><span>العربية</span><span>हिन्दी</span><span>ગુજરાતી</span><span>اردو</span><span>Kiswahili</span><span>Chinyanja</span>
            </div>
            <p style="font-size:.85rem;color:var(--s-ink-3);margin:0">Automatic translation by Google. Try it with the language button at the top of this page.</p>
        </div>
    </div>
</section>

{{-- =========================== WHO IT'S FOR =========================== --}}
<section class="sec">
    <div class="wrap">
        <div class="sec-head">
            <span class="kicker">Who it's for</span>
            <h2>From a five-person clinic to a 500-person contractor.</h2>
        </div>
        <div class="chips">
            @foreach(['Accounting & HR firms', 'Private schools & universities', 'Clinics, hospitals & pharmacies', 'Mining contractors', 'Construction (incl. Chinese-owned firms)', 'Logistics & transport', 'Retail & convenience stores', 'Cleaning & security services', 'Law firms', 'NGOs', 'Farms & agro-processing', 'Hotels & lodges'] as $i)
                <span>{{ $i }}</span>
            @endforeach
        </div>
    </div>
</section>

{{-- =========================== PLANS TEASER =========================== --}}
<section class="sec sec-white">
    <div class="wrap">
        <div class="cta-band">
            <div>
                <h2>Free for up to 10 employees. Forever.</h2>
                <p>Bigger team? Get a dedicated workspace with your own web address and database.</p>
            </div>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                <a href="{{ route('register') }}" class="btn btn-copper">Start free</a>
                <a href="{{ route('site.pricing') }}" class="btn btn-ghost-light">See plans</a>
            </div>
        </div>
    </div>
</section>

{{-- =========================== FAQ =========================== --}}
<section class="sec" id="faq">
    <div class="wrap">
        <div class="sec-head"><span class="kicker">Questions</span><h2>Things people ask before switching.</h2></div>
        <div class="faq">
            @foreach($faqs as [$q, $a])
                <details @if($loop->first) open @endif><summary>{{ $q }}</summary><p>{{ $a }}</p></details>
            @endforeach
        </div>
    </div>
</section>

{{-- =========================== DEMO / CONTACT =========================== --}}
<section class="sec sec-white" id="demo">
    <div class="wrap contact-grid">
        <div>
            <span class="kicker">See it with your own numbers</span>
            <h2 style="font-size:clamp(1.9rem,3.4vw,2.5rem);margin-bottom:.9rem">Book a free walkthrough.</h2>
            <p style="color:var(--s-ink-2);font-size:1.05rem">We'll show you a full payroll month for a company like yours — and help move your employee list across. Resellers and accounting firms welcome.</p>
            <ul class="contact-list">
                <li><i class="bi bi-whatsapp"></i><a href="https://wa.me/{{ config('site.contact.whatsapp') }}" target="_blank" rel="noopener">WhatsApp {{ config('site.contact.phone') }}</a></li>
                <li><i class="bi bi-envelope"></i><a href="mailto:{{ config('site.contact.email') }}">{{ config('site.contact.email') }}</a></li>
                <li><i class="bi bi-geo-alt"></i><span>{{ config('site.contact.address') }} · {{ config('site.contact.hours') }}</span></li>
            </ul>
        </div>
        <div class="form-card">
            @if(session('demo_request_success'))
                <div class="note-ok">Thanks — your request is in. Check your email, and we'll be in touch within one business day.</div>
            @endif
            @if($errors->any())
                <div class="note-err">Please check the highlighted fields and try again.</div>
            @endif
            <form action="{{ route('demo.request.store') }}" method="POST" novalidate id="demoForm">
                @csrf
                <div class="hp" aria-hidden="true"><label for="website">Leave this empty</label><input type="text" name="website" id="website" tabindex="-1" autocomplete="off"></div>
                <div class="fgrid">
                    <div class="fld"><label for="full_name">Your name *</label><input id="full_name" name="full_name" value="{{ old('full_name') }}" required autocomplete="name">@error('full_name')<div class="err">{{ $message }}</div>@enderror</div>
                    <div class="fld"><label for="email">Work email *</label><input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email">@error('email')<div class="err">{{ $message }}</div>@enderror</div>
                    <div class="fld"><label for="phone">Phone / WhatsApp</label><input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="+260 9XX XXX XXX" autocomplete="tel"></div>
                    <div class="fld"><label for="company_name">Company *</label><input id="company_name" name="company_name" value="{{ old('company_name') }}" required autocomplete="organization">@error('company_name')<div class="err">{{ $message }}</div>@enderror</div>
                    <div class="fld"><label for="company_size">Employees</label>
                        <select id="company_size" name="company_size"><option value="">Select…</option>
                            @foreach(['1–10 employees', '11–50 employees', '51–200 employees', '201–500 employees', '500+ employees'] as $o)<option @selected(old('company_size') === $o)>{{ $o }}</option>@endforeach
                        </select></div>
                    <div class="fld"><label for="industry">Industry</label>
                        <select id="industry" name="industry"><option value="">Select…</option>
                            @foreach(['Accounting / HR firm', 'Reseller / partner', 'School or university', 'Clinic, hospital or pharmacy', 'Mining contractor', 'Construction', 'Logistics', 'Retail', 'Cleaning / security', 'Law firm', 'NGO', 'Agriculture', 'Hospitality', 'Other'] as $o)<option @selected(old('industry') === $o)>{{ $o }}</option>@endforeach
                        </select></div>
                    <div class="fld full"><label for="message">Anything we should cover?</label><textarea id="message" name="message" placeholder="e.g. we pay 3 branches and want to see leave and loans">{{ old('message') }}</textarea></div>
                </div>
                <button class="btn btn-primary" style="width:100%" type="submit">Request my demo</button>
            </form>
        </div>
    </div>
</section>

@if(config('site.demo_video'))
<div class="vmodal" id="vmodal" hidden onclick="if(event.target===this){this.hidden=true;document.getElementById('vframe').src=''}">
    <div class="vmodal-in"><button class="vmodal-x" type="button" aria-label="Close video" onclick="document.getElementById('vmodal').hidden=true;document.getElementById('vframe').src=''">×</button><iframe id="vframe" title="ZamPayroll demo" allow="autoplay; fullscreen" allowfullscreen></iframe></div>
</div>
@endif
@endsection

@push('scripts')
<script>
/* Hero: play the AI conversation once when it comes into view. */
(function () {
    const demo = document.getElementById('aiDemo');
    if (!demo) return;
    const steps = demo.querySelectorAll('.step');
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) { steps.forEach(s => s.classList.add('show')); return; }
    const delays = [300, 1300, 2700, 3700, 5800];
    let played = false;
    const play = () => {
        if (played) return; played = true;
        steps.forEach((s, i) => setTimeout(() => s.classList.add('show'), delays[i]));
        setTimeout(() => {
            const btns = document.getElementById('demoBtns');
            btns.querySelector('.ok').classList.add('pressed');
            setTimeout(() => btns.outerHTML = '<div class="d-done">✓ Confirmed</div>', 450);
        }, 4900);
    };
    if ('IntersectionObserver' in window) {
        new IntersectionObserver((e, o) => { if (e[0].isIntersecting) { play(); o.disconnect(); } }, { threshold: .35 }).observe(demo);
    } else play();
})();
</script>
@endpush
