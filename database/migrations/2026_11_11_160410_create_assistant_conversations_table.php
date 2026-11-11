<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('channel', 20)->default('site');
            $table->char('session_hash', 64);
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('state', 40)->default('greeting');
            $table->string('status', 20)->default('active');
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('service_request_id')->nullable()->constrained('service_requests')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('collected')->nullable();
            $table->string('handoff_reason', 255)->nullable();
            $table->unsignedSmallInteger('invalid_attempts')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('retention_until')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'session_hash']);
            $table->index(['tenant_id', 'status', 'last_activity_at']);
            $table->index(['tenant_id', 'retention_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_conversations');
    }
};
