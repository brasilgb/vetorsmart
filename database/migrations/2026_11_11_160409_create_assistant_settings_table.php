<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('display_name', 120)->nullable();
            $table->string('greeting', 500)->nullable();
            $table->text('presentation')->nullable();
            $table->string('service_region', 255)->nullable();
            $table->string('business_hours_text', 255)->nullable();
            $table->text('commercial_info')->nullable();
            $table->text('guidance')->nullable();
            $table->text('handoff_conditions')->nullable();
            $table->unsignedInteger('daily_message_limit')->default(200);
            $table->unsignedInteger('daily_token_limit')->default(100000);
            $table->unsignedSmallInteger('retention_days')->default(30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_settings');
    }
};
