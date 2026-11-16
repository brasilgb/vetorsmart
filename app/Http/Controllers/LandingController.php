<?php

namespace App\Http\Controllers;

use App\Models\Segment;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The commercial landing page of the central host. Company hostnames never reach it: their routes are registered
 * first (routes/web.php) and serve the company site or redirect to it.
 */
class LandingController extends Controller
{
    public function __invoke(): Response
    {
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
