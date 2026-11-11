<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Service requests of a tenant. Composite foreign keys tie the customer, service and
     * professional to the same tenant, so a request can never point at another company's data.
     */
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedBigInteger('professional_id')->nullable();
            $table->text('description');
            $table->string('origin', 20)->default('panel');
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('requested');
            $table->string('closing_reason', 500)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers');
            $table->foreign(['tenant_id', 'service_id'])->references(['tenant_id', 'id'])->on('services');
            $table->foreign(['tenant_id', 'professional_id'])->references(['tenant_id', 'id'])->on('professionals');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE service_requests ADD CONSTRAINT service_requests_priority_valid CHECK (priority IN ('low','normal','high','urgent'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
