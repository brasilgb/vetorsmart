<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Site\SiteController;
use App\Models\Segment;
use App\Services\Websites\WebsiteResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The central host shows the commercial landing page. A company's hostname shows its
 * published site instead, and anything unknown answers with a plain 404.
 */
class LandingController extends Controller
{
    public function __invoke(Request $request, SiteController $site): mixed
    {
        if ($request->getHost() !== (string) config('platform.central_host')) {
            return $site->show($request, app(WebsiteResolver::class));
        }

        return Inertia::render('welcome', [
            'segments' => Segment::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'description'])
                ->map(fn (Segment $segment): array => [
                    'id' => $segment->id,
                    'name' => $segment->name,
                    'description' => $segment->description,
                ])
                ->values()
                ->all(),
        ]);
    }
}
