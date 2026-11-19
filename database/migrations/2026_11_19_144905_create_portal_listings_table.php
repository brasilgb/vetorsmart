<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Regional Portal.
     *
     * - tenant_websites.portal_listed: the company's consent to appear in the directory. Off by default, so no company
     *   is exposed without choosing it.
     * - portal_listings: search index of the directory, one row per company that opted in, built only from its
     *   published version (never from the draft or internal records). Eligibility (active company, published site,
     *   consent, subscription) is checked again on every query, so a stale row never shows a company.
     */
    public function up(): void
    {
        Schema::table('tenant_websites', function (Blueprint $table) {
            $table->boolean('portal_listed')->default(false)->after('status');
            $table->timestamp('portal_listed_at')->nullable()->after('portal_listed');
        });

        Schema::create('portal_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('website_version_id')->constrained('website_versions')->cascadeOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained('segments')->nullOnDelete();
            $table->string('business_name', 160);
            $table->string('name_search', 160);
            $table->string('segment_search', 120)->default('');
            $table->string('city', 120);
            $table->string('city_search', 120);
            $table->char('state', 2);
            $table->string('region', 255)->nullable();
            $table->string('region_search', 255)->default('');
            $table->string('summary', 280)->nullable();
            $table->json('services');
            $table->text('services_search');
            $table->json('logo')->nullable();
            $table->unsignedTinyInteger('completeness')->default(0);
            $table->timestamps();

            $table->index(['state', 'city_search']);
            $table->index('segment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_listings');

        Schema::table('tenant_websites', function (Blueprint $table) {
            $table->dropColumn(['portal_listed', 'portal_listed_at']);
        });
    }
};
