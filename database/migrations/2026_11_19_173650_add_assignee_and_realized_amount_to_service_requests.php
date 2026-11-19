<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Responsible person and realized amount of a service request.
     *
     * - assigned_user_id: the member of the company responsible for the request (not the professional of the
     *   appointment). Membership of the same company is checked by ServiceRequestService on every assignment.
     * - realized_amount_cents: the amount the company declares it received for a completed service, in cents (no
     *   floating point). NULL means unknown, which is different from zero. Existing requests stay NULL: no value is
     *   invented for the past.
     */
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('professional_id')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_user_id');
            $table->unsignedBigInteger('realized_amount_cents')->nullable()->after('closing_reason');
            $table->timestamp('realized_amount_recorded_at')->nullable()->after('realized_amount_cents');
            $table->foreignId('realized_amount_recorded_by')->nullable()->after('realized_amount_recorded_at')->constrained('users')->nullOnDelete();

            $table->index(['tenant_id', 'assigned_user_id']);
            $table->index(['tenant_id', 'status', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status', 'closed_at']);
            $table->dropIndex(['tenant_id', 'assigned_user_id']);
            $table->dropConstrainedForeignId('realized_amount_recorded_by');
            $table->dropColumn(['realized_amount_cents', 'realized_amount_recorded_at']);
            $table->dropColumn('assigned_at');
            $table->dropConstrainedForeignId('assigned_user_id');
        });
    }
};
