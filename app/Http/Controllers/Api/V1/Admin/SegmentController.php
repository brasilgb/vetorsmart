<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSegmentRequest;
use App\Http\Requests\Api\V1\UpdateSegmentRequest;
use App\Http\Resources\Api\V1\SegmentResource;
use App\Models\Segment;
use App\Models\User;
use App\Services\SegmentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SegmentController extends Controller
{
    /**
     * List all segments.
     */
    public function index(): AnonymousResourceCollection
    {
        return SegmentResource::collection(Segment::query()->orderBy('name')->paginate(50));
    }

    /**
     * Create a segment.
     */
    public function store(StoreSegmentRequest $request, SegmentService $segments): SegmentResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new SegmentResource($segments->create(
            name: $request->string('name')->value(),
            description: $request->string('description')->value() ?: null,
            active: $request->boolean('active', true),
            actor: $actor,
        ));
    }

    /**
     * Update a segment. Fields not sent keep their current value.
     */
    public function update(UpdateSegmentRequest $request, Segment $segment, SegmentService $segments): SegmentResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new SegmentResource($segments->update(
            segment: $segment,
            name: $request->has('name') ? $request->string('name')->value() : $segment->name,
            description: $request->has('description') ? ($request->string('description')->value() ?: null) : $segment->description,
            active: $request->has('active') ? $request->boolean('active') : $segment->active,
            actor: $actor,
        ));
    }
}
