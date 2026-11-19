<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Start of billing enforcement. Empty means billing is not in force: trials never end in suspension, no charge is
     * opened and no paid seat or credit is granted. It is set only by the audited billing:start-enforcement command,
     * after the operational prerequisites, together with PLATFORM_BILLING_ENFORCEMENT_ENABLED=true.
     */
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->timestamp('enforcement_starts_at')->nullable()->after('ai_credit_purchases_enabled');
            $table->foreignId('enforcement_scheduled_by')->nullable()->after('enforcement_starts_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enforcement_scheduled_by');
            $table->dropColumn('enforcement_starts_at');
        });
    }
};
