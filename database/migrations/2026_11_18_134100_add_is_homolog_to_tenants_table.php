<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Internal mark of a homologation company. Until now the "[HOMOLOG]" prefix of the name was the only sign, and the
     * public registration could produce it. The mark is set only from the terminal (homolog:mark), and the companies
     * already named with the prefix receive it here, so the indicators and the purge keep the same set.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('is_homolog')->default(false)->after('status')->index();
        });

        DB::table('tenants')->where('name', 'like', '[HOMOLOG]%')->update(['is_homolog' => true]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['is_homolog']);
            $table->dropColumn('is_homolog');
        });
    }
};
