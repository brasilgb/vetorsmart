<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Only settings that belong to the service configuration are added here.
     * Phone and timezone already live in `tenants` and are not duplicated.
     */
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->text('description')->nullable()->after('business_name');
            $table->json('service_modes')->nullable()->after('state');
            $table->text('welcome_message')->nullable()->after('service_modes');
            $table->string('confirmation_policy', 40)->default('manual')->after('welcome_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['description', 'service_modes', 'welcome_message', 'confirmation_policy']);
        });
    }
};
