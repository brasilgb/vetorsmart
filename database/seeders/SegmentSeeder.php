<?php

namespace Database\Seeders;

use App\Models\Segment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SegmentSeeder extends Seeder
{
    /**
     * Initial global segments. Keyed by slug so re-running never duplicates rows.
     *
     * @var list<array{name: string, description: string}>
     */
    private const array SEGMENTS = [
        ['name' => 'Climatização', 'description' => 'Instalação e manutenção de climatização.'],
        ['name' => 'Eletricistas', 'description' => 'Serviços elétricos residenciais e comerciais.'],
        ['name' => 'Encanadores', 'description' => 'Serviços hidráulicos e encanamento.'],
        ['name' => 'Oficinas', 'description' => 'Oficinas mecânicas e de manutenção.'],
        ['name' => 'Assistência técnica', 'description' => 'Assistência técnica de equipamentos.'],
        ['name' => 'Jardinagem', 'description' => 'Paisagismo e jardinagem.'],
        ['name' => 'Limpeza', 'description' => 'Serviços de limpeza.'],
        ['name' => 'Serviços gerais', 'description' => 'Serviços gerais e manutenção predial.'],
        ['name' => 'Beleza e estética', 'description' => 'Salões, estética e cuidados pessoais.'],
        ['name' => 'Serviços profissionais', 'description' => 'Consultorias e serviços profissionais por atendimento.'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::SEGMENTS as $segment) {
            Segment::query()->updateOrCreate(
                ['slug' => Str::slug($segment['name'])],
                ['name' => $segment['name'], 'description' => $segment['description'], 'active' => true],
            );
        }
    }
}
