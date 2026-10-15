@extends('layouts.onboarding')

@section('title', 'Email confirmation')

@section('aside')
    <h2>Your payroll, your workspace.</h2>
    <p class="lead-copy">Dedicated workspaces run on their own database and web address, so your data never sits next to anyone else's.</p>
@endsection

@section('content')
<div class="ob-status" style="margin-top:3rem">
    @switch($state)
        @case('dedicated_ready')
            <div class="ob-status-icon" aria-hidden="true">🎉</div>
            <h1 class="ob-title">{{ $company->name }} is live!</h1>
            <p class="ob-sub">Your email is confirmed and your dedicated workspace has been created at
                <strong>{{ $company->workspaceHost() }}</strong>. Bookmark it — that's where your team signs in from now on.</p>
            <ul class="ob-checklist">
                <li><i class="bi bi-database-check"></i> Private database created</li>
                <li><i class="bi bi-person-check"></i> Your administrator account moved in</li>
                <li><i class="bi bi-calculator"></i> Zambian payroll rules (PAYE, NAPSA, NHIMA) loaded</li>
            </ul>
            <a class="zp-btn zp-btn-primary" id="enterBtn" href="{{ $handoff }}"><i class="bi bi-box-arrow-in-right"></i> Enter my workspace</a>
            <p class="zp-hint" id="countdown" style="margin-top:1rem">Taking you there in <b>5</b> seconds…</p>
            @push('scripts')
            <script>
                ZPConfetti.burst();
                let n = 5; const el = document.querySelector('#countdown b');
                const t = setInterval(() => { n--; el.textContent = n; if (n <= 0) { clearInterval(t); window.location = @json($handoff); } }, 1000);
            </script>
            @endpush
            @break

        @case('awaiting')
            <div class="ob-status-icon copper" aria-hidden="true">🛠️</div>
            <h1 class="ob-title">Email confirmed — we're preparing your workspace</h1>
            <p class="ob-sub">Our team is setting up the dedicated database for <strong>{{ $company->name }}</strong>.
                We'll email you as soon as <strong>{{ $company->workspaceHost() }}</strong> is ready, usually within one business day.</p>
            <a class="zp-btn zp-btn-ghost" href="{{ route('home') }}">Back to the website</a>
            @break

        @case('failed')
            <div class="ob-status-icon red" aria-hidden="true">⚠️</div>
            <h1 class="ob-title">Your email is confirmed, but the workspace isn't ready</h1>
            <p class="ob-sub">We couldn't finish creating <strong>{{ $company->workspaceHost() }}</strong> automatically. Your details are saved and our team has been alerted —
                you don't need to register again. Contact <a href="mailto:support@zampayroll.com">support@zampayroll.com</a> if you need it urgently.</p>
            @break

        @default
            <div class="ob-status-icon red" aria-hidden="true">🔗</div>
            <h1 class="ob-title">This link has expired or was already used</h1>
            <p class="ob-sub">If you've already confirmed your email, just sign in. Otherwise request a new confirmation link.</p>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                <a class="zp-btn zp-btn-primary" href="{{ route('login') }}">Sign in</a>
                <a class="zp-btn zp-btn-ghost" href="{{ route('verification.notice') }}">Send a new link</a>
            </div>
    @endswitch
</div>
@endsection
