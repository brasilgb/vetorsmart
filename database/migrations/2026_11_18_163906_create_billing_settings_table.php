<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single row with the billing rules and the Efí credentials. Credentials, certificate and webhook secret are
     * stored encrypted with the application key (cast `encrypted`) and are never sent back to a screen. The
     * environment starts in homologation: no real charge is possible until the RootAdmin switches it.
     */
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('efi_environment', 20)->default('homologation');
            $table->text('efi_pix_client_id')->nullable();
            $table->text('efi_pix_client_secret')->nullable();
            $table->text('efi_pix_certificate')->nullable();
            $table->string('efi_pix_key', 120)->nullable();
            $table->text('efi_charges_client_id')->nullable();
            $table->text('efi_charges_client_secret')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->unsignedTinyInteger('grace_days')->default(5);
            $table->unsignedTinyInteger('renewal_days_before')->default(7);
            $table->unsignedTinyInteger('billet_due_days')->default(5);
            $table->timestamp('connection_tested_at')->nullable();
            $table->timestamps();
        });

        DB::table('billing_settings')->insert(['efi_environment' => 'homologation', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
