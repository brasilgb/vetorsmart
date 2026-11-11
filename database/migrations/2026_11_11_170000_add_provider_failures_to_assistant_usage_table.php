<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_usage', function (Blueprint $table) {
            $table->unsignedInteger('provider_failures')->default(0)->after('provider_calls');
        });
    }

    public function down(): void
    {
        Schema::table('assistant_usage', function (Blueprint $table) {
            $table->dropColumn('provider_failures');
        });
    }
};
