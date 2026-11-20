<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How the customer found the company (discovery), kept apart from the channel of the request (origin): a visitor
     * who arrived from the Regional Portal and then used the assistant is origin "ai" with discovery "portal". Without
     * this column one of the two facts would be lost. The conversation carries it until its request is registered.
     *
     * Existing requests with origin "portal" came, by definition, through the portal: they get discovery "portal".
     * Every other existing row stays NULL (unknown), never guessed.
     */
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('discovery', 20)->nullable()->after('origin');
            $table->index(['tenant_id', 'discovery', 'created_at']);
        });

        Schema::table('assistant_conversations', function (Blueprint $table) {
            $table->string('discovery', 20)->nullable()->after('channel');
        });

        DB::table('service_requests')->where('origin', 'portal')->update(['discovery' => 'portal']);
    }

    public function down(): void
    {
        Schema::table('assistant_conversations', function (Blueprint $table) {
            $table->dropColumn('discovery');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'discovery', 'created_at']);
            $table->dropColumn('discovery');
        });
    }
};
