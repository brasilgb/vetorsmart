<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A platform subdomain the company stopped using. The row is kept, so the old address keeps redirecting to the
     * current one and no other company can claim the name. Additive and nullable: existing rows stay current.
     */
    public function up(): void
    {
        Schema::table('tenant_domains', function (Blueprint $table) {
            $table->timestamp('retired_at')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_domains', function (Blueprint $table) {
            $table->dropColumn('retired_at');
        });
    }
};
