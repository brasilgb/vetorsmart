<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Official municipalities and the area a company serves.
     *
     * - municipalities: the IBGE registry (code, name, state), loaded from database/data/municipios-ibge.csv. The code
     *   is the primary key, so every reference is the official identifier. name_search is the normalized name used by
     *   the portal (PortalText::normalize), so "São Paulo", "sao paulo" and "SAO-PAULO" are the same municipality.
     * - portal_listings.municipality_id: the municipality where the company is based, when its published city matches
     *   the registry. Nullable: a listing built from a city that is not in the registry keeps working by name.
     * - portal_listing_areas: the municipalities a company declared it serves, from its published site only. Searched
     *   by municipality, so a company based in Montenegro (RS) appears in a search for Salvador do Sul (RS).
     * - service_requests.municipality_id: the municipality the visitor confirmed for the service. Only the municipality
     *   is kept, never coordinates. Existing requests stay NULL (unknown).
     */
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name', 80);
            $table->string('name_search', 80);
            $table->char('state', 2);

            $table->index(['state', 'name_search']);
            $table->index('name_search');
        });

        $this->loadRegistry();

        Schema::table('portal_listings', function (Blueprint $table) {
            $table->unsignedInteger('municipality_id')->nullable()->after('state');
            $table->foreign('municipality_id')->references('id')->on('municipalities')->nullOnDelete();
        });

        Schema::create('portal_listing_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_listing_id')->constrained('portal_listings')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedInteger('municipality_id');
            $table->foreign('municipality_id')->references('id')->on('municipalities')->cascadeOnDelete();

            $table->unique(['portal_listing_id', 'municipality_id']);
            $table->index('municipality_id');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->unsignedInteger('municipality_id')->nullable()->after('utm_campaign');
            $table->foreign('municipality_id')->references('id')->on('municipalities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropForeign(['municipality_id']);
            $table->dropColumn('municipality_id');
        });

        Schema::dropIfExists('portal_listing_areas');

        Schema::table('portal_listings', function (Blueprint $table) {
            $table->dropForeign(['municipality_id']);
            $table->dropColumn('municipality_id');
        });

        Schema::dropIfExists('municipalities');
    }

    private function loadRegistry(): void
    {
        $handle = fopen(database_path('data/municipios-ibge.csv'), 'r');

        if ($handle === false) {
            throw new RuntimeException('database/data/municipios-ibge.csv not found.');
        }

        fgetcsv($handle, escape: '');
        $rows = [];

        while (($line = fgetcsv($handle, escape: '')) !== false) {
            if (count($line) !== 3) {
                continue;
            }

            [$code, $name, $state] = $line;
            $rows[] = ['id' => (int) $code, 'name' => $name, 'name_search' => self::normalize($name), 'state' => $state];

            if (count($rows) === 500) {
                DB::table('municipalities')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('municipalities')->insert($rows);
        }

        fclose($handle);
    }

    /**
     * Same steps as PortalText::normalize, repeated here so the migration never depends on application code.
     */
    private static function normalize(string $text): string
    {
        $ascii = strtolower(Str::ascii($text));

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^a-z0-9]+/', ' ', $ascii)));
    }
};
