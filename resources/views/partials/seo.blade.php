{{--
    SEO + social meta. Pass: $seo = ['title','description','path','image','type','noindex','schema'=>[...]]
--}}
@php
    $site = config('site');
    $seo = $seo ?? [];
    $seoTitle = $seo['title'] ?? ($site['name'] . ' — ' . $site['tagline']);
    $seoDesc = $seo['description'] ?? $site['description'];
    $seoUrl = $site['url'] . ($seo['path'] ?? '/' . ltrim(request()->path() === '/' ? '' : request()->path(), '/'));
    $seoImage = $seo['image'] ?? $site['og_image'];
    $sameAs = array_values(array_filter($site['social']));
    $baseSchema = [
        [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $site['name'],
            'url' => $site['url'],
            'logo' => $site['logo'],
            'email' => $site['contact']['email'],
            'telephone' => $site['contact']['phone'],
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Lusaka', 'addressCountry' => 'ZM'],
            'sameAs' => $sameAs,
            'contactPoint' => [['@type' => 'ContactPoint', 'telephone' => $site['contact']['phone'], 'contactType' => 'sales', 'areaServed' => 'ZM', 'availableLanguage' => ['English']]],
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $site['name'],
            'url' => $site['url'],
            'inLanguage' => 'en-ZM',
        ],
    ];
    $schemas = array_merge($baseSchema, $seo['schema'] ?? []);
@endphp
<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDesc }}">
<meta name="robots" content="{{ !empty($seo['noindex']) ? 'noindex, nofollow' : 'index, follow, max-image-preview:large' }}">
<link rel="canonical" href="{{ $seoUrl }}">
<meta name="theme-color" content="#0A3B22">
<meta name="application-name" content="{{ $site['name'] }}">
<meta name="geo.region" content="ZM">
<meta name="geo.placename" content="Lusaka">

<meta property="og:type" content="{{ $seo['type'] ?? 'website' }}">
<meta property="og:site_name" content="{{ $site['name'] }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDesc }}">
<meta property="og:url" content="{{ $seoUrl }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $seoTitle }}">
<meta property="og:locale" content="{{ $site['locale'] }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDesc }}">
<meta name="twitter:image" content="{{ $seoImage }}">

<link rel="icon" href="{{ asset('favicon.ico') }}">
<link rel="apple-touch-icon" href="{{ asset('assets/images/favicon/apple-icon-180x180.png') }}">

@foreach($schemas as $schema)
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}</script>
@endforeach
