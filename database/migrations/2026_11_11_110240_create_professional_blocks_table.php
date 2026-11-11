<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agenda blocks (time off, lunch, unavailable periods) of a professional.
     */
    public function up(): void
    {
        Schema::create('professional_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->unsignedBigInteger('professional_id');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason', 120)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'professional_id', 'starts_at']);
            $table->foreign(['tenant_id', 'professional_id'])->references(['tenant_id', 'id'])->on('professionals')->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE professional_blocks ADD CONSTRAINT professional_blocks_window_order CHECK (ends_at > starts_at)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_blocks');
    }
};
