<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One public site per company. The draft is stored apart from the published versions,
     * so unpublished edits never reach visitors.
     */
    public function up(): void
    {
        Schema::create('tenant_websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants');
            $table->string('name', 120);
            $table->string('template', 40)->default('base');
            $table->string('status', 20)->default('draft');
            $table->json('draft_content');
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->timestamp('configured_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('unpublished_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_websites');
    }
};
