<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campaign tags (UTM) of the visit that originated a request, kept as complementary information of the commercial
     * origin. No existing column can hold them: "origin" is the channel and "discovery" only the verified portal arrival.
     *
     * - Captured from the first tagged visit to the company's public pages (first touch of the visit), sanitised and
     *   limited to 100 characters; no personal data.
     * - Declared by the link the visitor followed: they never prove a privileged origin such as the Regional Portal.
     * - Written once, when the request (or the conversation that leads to it) is created; never overwritten later.
     * - Existing rows stay NULL (no campaign known); nothing is inferred for the past.
     */
    public function up(): void
    {
        foreach (['service_requests' => 'discovery', 'assistant_conversations' => 'discovery'] as $table => $after) {
            Schema::table($table, function (Blueprint $blueprint) use ($after) {
                $blueprint->string('utm_source', 100)->nullable()->after($after);
                $blueprint->string('utm_medium', 100)->nullable()->after('utm_source');
                $blueprint->string('utm_campaign', 100)->nullable()->after('utm_medium');
            });
        }

        Schema::table('service_requests', function (Blueprint $table) {
            $table->index(['tenant_id', 'utm_source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'utm_source', 'created_at']);
        });

        foreach (['service_requests', 'assistant_conversations'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['utm_source', 'utm_medium', 'utm_campaign']);
            });
        }
    }
};
