<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * E-mail confirmation starts with this release. Accounts that already exist were created without it (open public
     * registration, team-created users), so their address was never proven. They are NOT marked as verified: they
     * receive a separate legacy flag that keeps their current access to the panel while they confirm the address
     * through the normal link, and sensitive operations (RootAdmin, billing, team management) require the real
     * confirmation. Existing confirmations (email_verified_at already set) are kept untouched.
     *
     * Replaces mark_existing_users_as_verified (never executed in production), which would have recorded a false
     * confirmation for every existing account.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verification_legacy_at')->nullable()->after('email_verified_at');
        });

        DB::table('users')->whereNull('email_verified_at')->update(['email_verification_legacy_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verification_legacy_at');
        });
    }
};
