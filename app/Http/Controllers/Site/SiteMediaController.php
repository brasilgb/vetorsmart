<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Websites\PublicSiteContext;
use App\Services\Websites\WebsiteMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Images of a published company site. A file is served only when the version visitors see right now references it:
 * a draft image, an image of another company or a removed one answers with a plain 404. Names are random and never
 * reused, so the response can be cached for a long time.
 */
class SiteMediaController extends Controller
{
    public function __invoke(Request $request, PublicSiteContext $sites, WebsiteMedia $media): Response
    {
        $file = (string) $request->route('file');
        $site = $sites->forRequest($request);

        if ($site === null || ! $media->isValidName($file) || ! $media->isReferenced($site['content'], $file)) {
            abort(404);
        }

        $path = $media->path($site['tenant'], $file);

        if (! Storage::disk(WebsiteMedia::DISK)->exists($path)) {
            abort(404);
        }

        return Storage::disk(WebsiteMedia::DISK)->response($path, $file, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
