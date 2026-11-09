<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Composite foreign keys tie both sides of the link to the same tenant.
     */
    public function up(): void
    {
        Schema::create('professional_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->unsignedBigInteger('professional_id');
            $table->unsignedBigInteger('service_id');
            $table->timestamps();

            $table->unique(['professional_id', 'service_id']);
            $table->index(['tenant_id', 'service_id']);
            $table->foreign(['tenant_id', 'professional_id'])
                ->references(['tenant_id', 'id'])
                ->on('professionals')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'service_id'])
                ->references(['tenant_id', 'id'])
                ->on('services')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_services');
    }
};
