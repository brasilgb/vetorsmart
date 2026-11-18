<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commercial plans. Amounts are integers in cents. The main plan is created without a price: commercial values
     * are not approved yet, so the trial works and contracting stays closed until the RootAdmin sets the price.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('price_cents')->nullable();
            $table->unsignedTinyInteger('interval_months')->default(1);
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->unsignedSmallInteger('included_users')->nullable();
            $table->unsignedInteger('extra_user_price_cents')->nullable();
            $table->unsignedInteger('ai_messages_per_month')->nullable();
            $table->unsignedInteger('promotional_price_cents')->nullable();
            $table->date('promotional_until')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        DB::table('plans')->insert([
            'code' => 'principal',
            'name' => 'VetorSmart',
            'description' => 'Plano principal: acesso a todas as funcionalidades do painel.',
            'trial_days' => 14,
            'interval_months' => 1,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
