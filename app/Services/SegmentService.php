<?php

namespace App\Services;

use App\Models\Segment;
use App\Models\User;
use Illuminate\Support\Str;

final class SegmentService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * Create a global segment.
     */
    public function create(string $name, ?string $description, bool $active, User $actor): Segment
    {
        $segment = Segment::create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'description' => $description,
            'active' => $active,
        ]);

        $this->auditLogger->record('segment.created', null, $segment, ['name' => $segment->name], $actor);

        return $segment;
    }

    /**
     * Update a global segment. The slug is kept stable after creation.
     */
    public function update(Segment $segment, string $name, ?string $description, bool $active, User $actor): Segment
    {
        $segment->fill([
            'name' => $name,
            'description' => $description,
            'active' => $active,
        ]);

        $changedFields = array_keys($segment->getDirty());
        $segment->save();

        if ($changedFields !== []) {
            $this->auditLogger->record('segment.updated', null, $segment, ['fields' => $changedFields], $actor);
        }

        return $segment;
    }

    /**
     * Build a slug unique across segments, appending a short suffix on collision.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'segmento';
        $slug = $base;

        while (Segment::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
