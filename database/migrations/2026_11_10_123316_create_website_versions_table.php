<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable snapshots of each publication. The live site always reads one of these,
     * so publishing is a single pointer change inside a transaction.
     */
    public function up(): void
    {
        Schema::create('website_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->unsignedBigInteger('tenant_website_id');
            $table->unsignedInteger('version');
            $table->json('content');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');

            $table->unique(['tenant_website_id', 'version'], 'website_versions_site_version_unique');
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'tenant_website_id'])
                ->references(['tenant_id', 'id'])
                ->on('tenant_websites');
        });

        Schema::table('tenant_websites', function (Blueprint $table) {
            $table->foreign(['tenant_id', 'published_version_id'])
                ->references(['tenant_id', 'id'])
                ->on('website_versions');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE website_versions ADD CONSTRAINT website_versions_version_positive CHECK (version > 0)');
        }
    }

    public function down(): void
    {
        Schema::table('tenant_websites', function (Blueprint $table) {
            $table->dropForeign(['tenant_id', 'published_version_id']);
        });
        Schema::dropIfExists('website_versions');
    }
};
