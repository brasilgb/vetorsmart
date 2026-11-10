<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Websites\WebsiteResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public site of a company, served on its verified hostname. Everything here comes from
 * the published snapshot; a missing or unauthorized host always answers with a plain 404.
 */
class SiteController extends Controller
{
    public function show(Request $request, WebsiteResolver $resolver): RedirectResponse|View
    {
        $site = $resolver->resolve($request->getHost());

        if ($site === null) {
            abort(404);
        }

        $primary = $site['primary'];

        // A visitor who arrives on a secondary domain is sent to the company's main address.
        if ($primary !== null && $primary->hostname !== $site['domain']->hostname) {
            return redirect()->to(config('platform.public_scheme', 'https').'://'.$primary->hostname.'/', 301);
        }

        $canonical = config('platform.public_scheme', 'https').'://'.$site['domain']->hostname.'/';

        return view('website.site', [
            'content' => $site['content'],
            'canonical' => $canonical,
            'noindex' => ! ($site['content']['seo']['indexable'] ?? true),
            'preview' => false,
        ]);
    }

    /**
     * Sitemap of a published, indexable site. Lists only the home page, the only page that exists.
     */
    public function sitemap(Request $request, WebsiteResolver $resolver): Response
    {
        $site = $resolver->resolve($request->getHost());

        if ($site === null) {
            abort(404);
        }

        $indexable = (bool) ($site['content']['seo']['indexable'] ?? true);
        $loc = e(config('platform.public_scheme', 'https').'://'.$site['domain']->hostname.'/');

        $body = $indexable
            ? '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>'.$loc.'</loc></url></urlset>'
            : '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';

        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * robots.txt for a company's site. The central host keeps the platform's original rules.
     */
    public function robots(Request $request, WebsiteResolver $resolver): Response
    {
        if ($request->getHost() === (string) config('platform.central_host')) {
            return response("User-agent: *\nDisallow:\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $site = $resolver->resolve($request->getHost());

        if ($site === null || ! ($site['content']['seo']['indexable'] ?? true)) {
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $base = config('platform.public_scheme', 'https').'://'.$site['domain']->hostname;

        return response("User-agent: *\nAllow: /\n\nSitemap: {$base}/sitemap.xml\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
