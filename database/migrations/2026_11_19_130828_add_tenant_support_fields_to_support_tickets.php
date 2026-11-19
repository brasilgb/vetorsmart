<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Central de Suporte: companies open and follow their own requests. A ticket gets a category, where it was opened
     * (company or platform), the platform person responsible and a due date set on the ticket (no contractual SLA).
     * Events gain an internal flag: internal notes of the platform team are never shown to the company. The history
     * stays append-only.
     */
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('category', 20)->default('other')->after('description');
            $table->string('opened_via', 20)->default('platform')->after('priority');
            $table->foreignId('assigned_to')->nullable()->after('opened_by')->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable()->after('assigned_to');
            $table->index(['due_at']);
        });

        Schema::table('support_ticket_events', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false)->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('support_ticket_events', function (Blueprint $table) {
            $table->dropColumn('is_internal');
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['due_at']);
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['category', 'opened_via', 'due_at']);
        });
    }
};
