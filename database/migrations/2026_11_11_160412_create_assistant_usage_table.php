<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->date('usage_date');
            $table->unsignedInteger('messages')->default(0);
            $table->unsignedInteger('provider_calls')->default(0);
            $table->unsignedBigInteger('tokens')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'usage_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_usage');
    }
};
