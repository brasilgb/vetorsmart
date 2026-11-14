<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_settings', function (Blueprint $table) {
            $table->string('privacy_policy_url', 500)->nullable()->after('handoff_conditions');
        });
    }

    public function down(): void
    {
        Schema::table('assistant_settings', function (Blueprint $table) {
            $table->dropColumn('privacy_policy_url');
        });
    }
};
