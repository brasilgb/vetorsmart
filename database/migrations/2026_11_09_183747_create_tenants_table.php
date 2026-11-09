<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('document', 32)->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->foreignId('segment_id')->nullable()->constrained('segments')->nullOnDelete();
            $table->string('status', 20)->default('active')->index();
            $table->string('timezone', 64)->default('America/Sao_Paulo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
