@php
    $seo = [
        'title' => 'AI mode — an HR & payroll assistant that does the work | ZamPayroll',
        'description' => 'Ask ZamPayroll AI who is on leave, what payroll cost last month, or tell it to add employees, approve leave and prepare payroll. Every change waits for your confirmation.',
        'path' => '/ai-assistant',
        'schema' => [[
            '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => config('site.url') . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'AI assistant', 'item' => config('site.url') . '/ai-assistant'],
            ],
        ]],
    ];
@endphp
@extends('layouts.site')

@section('content')
<section class="page-hero">
    <div class="wrap">
        <div class="crumbs"><a href="{{ route('home') }}">Home</a> / AI assistant</div>
        <h1>The fastest way through your HR to-do list.</h1>
        <p>AI mode sits on every screen. Ask it anything about your people and payroll, or tell it what to do — it prepares the work and waits for your OK.</p>
    </div>
</section>

<section class="sec ai-band" style="margin-top:32px">
    <div class="wrap">
        <div class="ai-grid">
            <div class="ai-col">
                <h3>Questions it answers instantly</h3>
                <div class="ai-q">
                    <div>“How many employees do we have, by department?”</div>
                    <div>“Who is on leave today?” · “…tomorrow?”</div>
                    <div>“How much was last month's payroll? How much PAYE and NAPSA?”</div>
                    <div>“What's waiting for my approval?”</div>
                    <div>“Who was absent today?” · “How much is owed on staff loans?”</div>
                </div>
            </div>
            <div class="ai-col">
                <h3>Work it does for you</h3>
                <div class="ai-q">
                    <div class="do">“Add these employees” — attach Excel/CSV or paste a list</div>
                    <div class="do">“Approve leave for Mary” · “Reject the overtime from Friday”</div>
                    <div class="do">“Prepare this month's payroll”</div>
                    <div class="do">“Create portals for everyone”</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="sec sec-white">
    <div class="wrap split">
        <div>
            <span class="kicker">Safe by design</span>
            <h2>It prepares. You decide.</h2>
            <ul class="ticks">
                <li><i class="bi bi-check-circle-fill"></i> Every change shows a preview card — nothing happens until you press Confirm.</li>
                <li><i class="bi bi-check-circle-fill"></i> It follows your company's roles: a manager can't run payroll through AI if their role doesn't allow it.</li>
                <li><i class="bi bi-check-circle-fill"></i> It only ever sees your own company's data.</li>
                <li><i class="bi bi-check-circle-fill"></i> It runs on ZamPayroll's servers — employee data isn't sent to outside AI services.</li>
            </ul>
            <a class="btn btn-primary" href="{{ route('register') }}">Try AI mode free</a>
        </div>
        <div>
            <div class="demo" style="max-width:460px;margin:0 auto">
                <div class="demo-flag"></div>
                <div class="demo-head">ZamPayroll AI <small>Example</small></div>
                <div class="demo-body" style="min-height:0">
                    <div class="d-user">Approve leave for Mary</div>
                    <div class="d-bot"><span class="av">✦</span><div style="flex:1">Here's the request — press Confirm to approve.
                        <div class="d-card action">
                            <div class="d-card-h"><span>⚡ Approve 1 leave request</span><span>Needs your OK</span></div>
                            <div class="d-row"><span>Mary Banda</span><span>Annual · 2–6 Nov (5 days)</span></div>
                            <div class="d-btns"><span class="ok">✓ Confirm</span><span class="no">Cancel</span></div>
                        </div></div></div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
