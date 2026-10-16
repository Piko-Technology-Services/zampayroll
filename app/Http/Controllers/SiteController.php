<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Public marketing pages + sitemap.
 */
class SiteController extends Controller
{
    public function features()
    {
        return view('site.features');
    }

    public function ai()
    {
        return view('site.ai');
    }

    public function pricing()
    {
        return view('site.pricing');
    }

    public function terms()
    {
        return view('site.terms');
    }

    public function privacy()
    {
        return view('site.privacy');
    }

    public function sitemap(): Response
    {
        $base = config('site.url');
        $updated = now()->startOfMonth()->toDateString();

        $pages = [
            ['/', '1.0', 'weekly'],
            ['/features', '0.9', 'monthly'],
            ['/ai-assistant', '0.8', 'monthly'],
            ['/pricing', '0.9', 'monthly'],
            ['/register', '0.7', 'yearly'],
            ['/login', '0.5', 'yearly'],
            ['/terms', '0.3', 'yearly'],
            ['/privacy', '0.3', 'yearly'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($pages as [$path, $priority, $freq]) {
            $xml .= "  <url><loc>{$base}{$path}</loc><lastmod>{$updated}</lastmod><changefreq>{$freq}</changefreq><priority>{$priority}</priority></url>\n";
        }
        $xml .= '</urlset>' . "\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
