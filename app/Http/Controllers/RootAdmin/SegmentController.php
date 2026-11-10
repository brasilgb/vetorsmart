<?php

namespace App\Http\Controllers\RootAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSegmentRequest;
use App\Http\Requests\Api\V1\UpdateSegmentRequest;
use App\Models\Segment;
use App\Models\User;
use App\Services\SegmentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SegmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('rootadmin/segments/index', [
            'segments' => Segment::query()
                ->withCount('tenants')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'description', 'active'])
                ->map(fn (Segment $segment): array => [
                    'id' => $segment->id,
                    'name' => $segment->name,
                    'slug' => $segment->slug,
                    'description' => $segment->description,
                    'active' => $segment->active,
                    'tenants' => $segment->tenants_count,
                ]),
        ]);
    }

    public function store(StoreSegmentRequest $request, SegmentService $segments): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $segments->create(
            name: $request->string('name')->value(),
            description: $request->string('description')->value() ?: null,
            active: $request->boolean('active', true),
            actor: $actor,
        );

        return back();
    }

    /**
     * Deactivation only: segments are never deleted, so existing companies keep their reference.
     */
    public function update(UpdateSegmentRequest $request, Segment $segment, SegmentService $segments): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $segments->update(
            segment: $segment,
            name: $request->has('name') ? $request->string('name')->value() : $segment->name,
            description: $request->has('description') ? ($request->string('description')->value() ?: null) : $segment->description,
            active: $request->has('active') ? $request->boolean('active') : $segment->active,
            actor: $actor,
        );

        return back();
    }
}
