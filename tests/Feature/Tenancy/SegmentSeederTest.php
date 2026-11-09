<?php

use App\Models\Segment;
use Database\Seeders\SegmentSeeder;

test('the initial segments are created', function () {
    $this->seed(SegmentSeeder::class);

    expect(Segment::query()->pluck('name')->sort()->values()->all())->toBe([
        'Assistência técnica',
        'Climatização',
        'Eletricistas',
        'Encanadores',
        'Jardinagem',
        'Limpeza',
        'Oficinas',
        'Serviços gerais',
    ]);
});

test('running the segment seeder again does not duplicate segments', function () {
    $this->seed(SegmentSeeder::class);
    $this->seed(SegmentSeeder::class);

    expect(Segment::query()->count())->toBe(8)
        ->and(Segment::query()->where('slug', 'assistencia-tecnica')->count())->toBe(1);
});
