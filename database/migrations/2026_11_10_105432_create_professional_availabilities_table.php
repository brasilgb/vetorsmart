<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Working windows of a professional. The composite foreign key keeps the
     * professional in the same tenant; the CHECK keeps every window well-formed.
     */
    public function up(): void
    {
        Schema::create('professional_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->unsignedBigInteger('professional_id');
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->unique(['professional_id', 'weekday', 'start_time'], 'prof_avail_unique');
            $table->index(['tenant_id', 'professional_id']);
            $table->foreign(['tenant_id', 'professional_id'])
                ->references(['tenant_id', 'id'])
                ->on('professionals')
                ->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE professional_availabilities ADD CONSTRAINT professional_availabilities_weekday_range CHECK (weekday <= 6)');
            DB::statement('ALTER TABLE professional_availabilities ADD CONSTRAINT professional_availabilities_window_order CHECK (end_time > start_time)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_availabilities');
    }
};
