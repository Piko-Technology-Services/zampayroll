@php
    $rateTxt = rtrim(rtrim(number_format($rate, 2), '0'), '.');
    $seo = [
        'title' => 'Become a ZamPayroll partner — earn recurring commission | ZamPayroll',
        'description' => 'HR consultancy firms, HR practitioners, HR students and freelancers: introduce Zambian companies to ZamPayroll with your own partner code and earn ' . $rateTxt . '% of every invoice they pay.',
        'path' => '/partners',
    ];
    $faqs = [
        ['Who can become a partner?', 'HR consultancy firms, HR practitioners, HR students, freelancers, accountants and payroll bureaus — anyone who talks to Zambian employers about people and pay.'],
        ['How do clients get linked to me?', 'You get a unique code (like ZP-CHANDA25) and a sign-up link. When a company signs up through your link, or types your code on the sign-up form, it is linked to you for good.'],
        ['How much do I earn?', $rateTxt . '% of every ZamPayroll invoice your clients pay (before VAT)' . ($months > 0 ? ', for their first ' . $months . ' months.' : ', for as long as they keep paying.') . ' Your dashboard shows every commission as it is earned.'],
        ['What if my client is on the free experience?', 'Teams of up to ' . $freeLimit . ' employees can try ZamPayroll free for 2 months, so there is no commission yet. As soon as they pay for a plan, you start earning.'],
        ['How and when am I paid?', 'Add your mobile money number or bank account on your dashboard. ZamPayroll pays out commission that is due and marks it paid, with a reference, so you can always check.'],
        ['Can I see my clients\' payroll?', 'No. Your dashboard shows each client\'s name, team size and plan, but never salaries or employee details. If a client wants your help inside ZamPayroll, they can invite you to their team.'],
    ];
@endphp
@extends('layouts.site')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <div class="crumbs"><a href="{{ route('home') }}">Home</a> / Partner programme</div>
        <h1>Know HR? Earn from it every month.</h1>
        <p>For HR consultancy firms, HR practitioners, HR students and freelancers. Introduce Zambian companies to ZamPayroll with your own code and earn {{ $rateTxt }}% of every invoice they pay.</p>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1.2rem">
            <a href="#apply" class="btn btn-primary"><i class="bi bi-person-plus"></i> Become a partner</a>
            <a href="https://wa.me/{{ config('site.contact.whatsapp') }}?text={{ urlencode('PARTNER') }}" target="_blank" rel="noopener" class="btn btn-line"><i class="bi bi-whatsapp"></i> Ask on WhatsApp</a>
        </div>
    </div>
</section>

<section class="sec" style="padding-top:28px">
    <div class="wrap">
        <div class="sec-head"><span class="kicker">How it works</span><h2>Sign up, share your code, earn every month.</h2></div>
        <div class="flow">
            <div class="flow-step"><div class="n">1</div><h3>Sign up</h3><p>Apply below in two minutes. {{ $auto ? 'You get your partner code straight away.' : 'We review it within one working day and email your code.' }}</p><span class="when">Today</span></div>
            <div class="flow-step"><div class="n">2</div><h3>Get your code</h3><p>A unique promo code and sign-up link, ready to share on WhatsApp, LinkedIn or email.</p><span class="when">Instantly</span></div>
            <div class="flow-step"><div class="n">3</div><h3>Share it</h3><p>Every company that signs up with your code or link is linked to you and shows up on your dashboard.</p><span class="when">Any time</span></div>
            <div class="flow-step"><div class="n">4</div><h3>Earn monthly</h3><p>{{ $rateTxt }}% of every invoice your clients pay{{ $months > 0 ? ' for their first ' . $months . ' months' : ', for as long as they stay' }}.</p><span class="when">Every month</span></div>
        </div>
    </div>
</section>

<section class="sec sec-white">
    <div class="wrap">
        <div class="mods">
            <div class="mod wide">
                <div>
                    <i class="bi bi-speedometer2 ic"></i>
                    <h3>Your partner dashboard</h3>
                    <p>Everything about your clients and your earnings in one place.</p>
                </div>
                <ul>
                    <li>Your code and link, with one-tap WhatsApp sharing</li>
                    <li>Every client: when they joined, team size and plan</li>
                    <li>Who is on a free trial and who is paying</li>
                    <li>Commission earned, waiting and paid out</li>
                </ul>
            </div>
            <div class="mod"><i class="bi bi-gift ic"></i><h3>Easy to sell</h3><p>Plans from K799 a month, and teams of up to {{ $freeLimit }} can try it free for 2 months.</p></div>
            <div class="mod"><i class="bi bi-person-video3 ic"></i><h3>Demo &amp; training</h3><p>A demo company to show the real thing, plus posters and videos for your pitch.</p></div>
            <div class="mod"><i class="bi bi-shield-check ic"></i><h3>We do the tech</h3><p>Hosting, updates, statutory changes and technical support are on us.</p></div>
            <div class="mod"><i class="bi bi-link-45deg ic"></i><h3>Clients stay yours</h3><p>A company that signs up with your code stays linked to you{{ $months > 0 ? ' for ' . $months . ' months' : '' }}.</p></div>
        </div>
    </div>
</section>

<section class="sec" id="apply">
    <div class="wrap contact-grid">
        <div>
            <span class="kicker">Apply</span>
            <h2 style="font-size:clamp(1.9rem,3.4vw,2.5rem);margin-bottom:.9rem">Become a ZamPayroll partner.</h2>
            <p style="color:var(--s-ink-2);font-size:1.05rem">Create your partner account. {{ $auto ? 'Confirm your email and your dashboard and code are ready.' : 'Confirm your email; we\'ll review your application and email your code.' }}</p>
            <ul class="contact-list">
                <li><i class="bi bi-whatsapp"></i><a href="https://wa.me/{{ config('site.contact.whatsapp') }}?text={{ urlencode('PARTNER') }}" target="_blank" rel="noopener">WhatsApp “PARTNER” to {{ config('site.contact.phone') }}</a></li>
                <li><i class="bi bi-envelope"></i><a href="mailto:{{ config('site.contact.email') }}">{{ config('site.contact.email') }}</a></li>
                <li><i class="bi bi-box-arrow-in-right"></i><span>Already a partner? <a href="{{ route('login') }}">Sign in</a></span></li>
            </ul>
        </div>
        <div class="form-card">
            @if($signedIn)
                <div class="note-ok">You're signed in as {{ $signedIn->email }}. To apply as a partner, sign out and use a separate email address.</div>
            @else
                @if($errors->any())<div class="note-err">Please check the highlighted fields and try again.</div>@endif
                <form action="{{ route('partners.store') }}" method="POST" novalidate>
                    @csrf
                    <div class="hp" aria-hidden="true"><label for="website">Leave this empty</label><input type="text" name="website" id="website" tabindex="-1" autocomplete="off"></div>
                    <div class="fgrid">
                        <div class="fld"><label for="name">Full name *</label><input id="name" name="name" value="{{ old('name') }}" required autocomplete="name">@error('name')<div class="err">{{ $message }}</div>@enderror</div>
                        <div class="fld"><label for="email">Email *</label><input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email">@error('email')<div class="err">{{ $message }}</div>@enderror</div>
                        <div class="fld"><label for="phone">Phone / WhatsApp *</label><input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="+260 9XX XXX XXX" required autocomplete="tel">@error('phone')<div class="err">{{ $message }}</div>@enderror</div>
                        <div class="fld"><label for="partner_type">I am a *</label>
                            <select id="partner_type" name="partner_type" required><option value="">Select…</option>
                                @foreach($types as $k => $label)<option value="{{ $k }}" @selected(old('partner_type') === $k)>{{ $label }}</option>@endforeach
                            </select>@error('partner_type')<div class="err">{{ $message }}</div>@enderror</div>
                        <div class="fld"><label for="firm_name">Firm or business name</label><input id="firm_name" name="firm_name" value="{{ old('firm_name') }}" autocomplete="organization"></div>
                        <div class="fld"><label for="city">Town / city</label><input id="city" name="city" value="{{ old('city') }}" placeholder="Lusaka"></div>
                        <div class="fld full"><label for="pitch">How will you find clients? <small style="font-weight:500">optional</small></label><textarea id="pitch" name="pitch" placeholder="e.g. I do HR consulting for 15 SMEs in Lusaka and Kitwe">{{ old('pitch') }}</textarea></div>
                        <div class="fld"><label for="password">Password *</label><input type="password" id="password" name="password" required autocomplete="new-password" minlength="8">@error('password')<div class="err">{{ $message }}</div>@enderror</div>
                        <div class="fld"><label for="password_confirmation">Confirm password *</label><input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"></div>
                        <div class="fld full">
                            <label style="display:flex;gap:.55rem;align-items:flex-start;font-weight:500"><input type="checkbox" name="terms" value="1" @checked(old('terms')) style="width:auto;margin-top:.25rem">
                                <span>I agree to the ZamPayroll <a href="{{ route('site.terms') }}" target="_blank">terms</a> and <a href="{{ route('site.privacy') }}" target="_blank">privacy policy</a>, and I'll represent ZamPayroll honestly to clients.</span></label>
                            @error('terms')<div class="err">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <button class="btn btn-primary" style="width:100%" type="submit"><i class="bi bi-person-badge"></i> Get my partner code</button>
                </form>
            @endif
        </div>
    </div>
</section>

<section class="sec sec-white" id="faq">
    <div class="wrap">
        <div class="sec-head"><span class="kicker">Questions</span><h2>What partners ask us.</h2></div>
        <div class="faq">
            @foreach($faqs as [$q, $a])
                <details @if($loop->first) open @endif><summary>{{ $q }}</summary><p>{{ $a }}</p></details>
            @endforeach
        </div>
    </div>
</section>
@endsection
