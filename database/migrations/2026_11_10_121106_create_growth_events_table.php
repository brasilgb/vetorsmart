<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only log of real business events that feed the Meu Crescimento panel.
     * Rows are never updated. The event type is limited to the GrowthEventType enum.
     */
    public function up(): void
    {
        Schema::create('growth_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('event_type', 60);
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['tenant_id', 'event_type', 'occurred_at'], 'growth_events_tenant_type_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('growth_events');
    }
};
