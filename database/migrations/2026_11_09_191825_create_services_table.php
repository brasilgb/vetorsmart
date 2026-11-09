<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The composite foreign key on (tenant_id, service_category_id) makes it
     * impossible for a service to reference a category of another tenant.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->unsignedBigInteger('service_category_id')->nullable();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('price_type', 20)->default('on_request');
            $table->boolean('requires_evaluation')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'active']);
            $table->index(['tenant_id', 'service_category_id']);
            $table->foreign(['tenant_id', 'service_category_id'])
                ->references(['tenant_id', 'id'])
                ->on('service_categories');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_price_non_negative CHECK (price IS NULL OR price >= 0)');
            DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_positive CHECK (duration_minutes IS NULL OR duration_minutes > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
