<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * E-mail verification starts with this release. The accounts that already exist were created before it and are
     * in use, so they are treated as verified instead of being locked out on deploy. Only accounts created from now
     * on have to confirm the address. Not reversible: a verification date is never removed from an account.
     */
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        //
    }
};
