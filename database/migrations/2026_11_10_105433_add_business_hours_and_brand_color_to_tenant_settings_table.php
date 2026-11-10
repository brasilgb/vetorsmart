<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Company opening hours (JSON, validated by the request) and the brand color,
     * which must be one of the approved palette tokens.
     */
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->json('business_hours')->nullable()->after('confirmation_policy');
            $table->string('brand_color', 7)->default('#2563EB')->after('business_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['business_hours', 'brand_color']);
        });
    }
};
