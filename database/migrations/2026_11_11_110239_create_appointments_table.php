<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Appointments of a service request with one professional. Conflicts are prevented in the
     * service layer, under a row lock on the professional; the indexes keep that lookup fast.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->unsignedBigInteger('service_request_id');
            $table->unsignedBigInteger('professional_id');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('scheduled');
            $table->string('cancelling_reason', 500)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'starts_at']);
            $table->index(['tenant_id', 'professional_id', 'starts_at']);
            $table->index(['tenant_id', 'service_request_id']);
            $table->foreign(['tenant_id', 'service_request_id'])->references(['tenant_id', 'id'])->on('service_requests')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'professional_id'])->references(['tenant_id', 'id'])->on('professionals');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_window_order CHECK (ends_at > starts_at)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
