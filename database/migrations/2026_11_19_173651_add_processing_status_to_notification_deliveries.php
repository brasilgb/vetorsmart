<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Processing state of each operational notice, so a failure is visible and can be reprocessed once.
     *
     * - status: processing, processed or failed (rows that existed before are marked processed);
     * - event, subject_id, actor_id: what to rebuild when a failed notice is reprocessed;
     * - mail_queued / mail_sent / mail_failed: e-mails put in the queue for this notice and their real outcome, as
     *   reported by the mail channel (sent only when the server accepted the message, never assumed).
     * - last_error: the class of the last error only, never a message that could carry personal data.
     */
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->string('status', 20)->default('processed')->after('tenant_id');
            $table->string('event', 40)->nullable()->after('status');
            $table->unsignedBigInteger('subject_id')->nullable()->after('event');
            $table->unsignedBigInteger('actor_id')->nullable()->after('subject_id');
            $table->unsignedTinyInteger('attempts')->default(0)->after('actor_id');
            $table->unsignedSmallInteger('mail_queued')->default(0)->after('attempts');
            $table->unsignedSmallInteger('mail_sent')->default(0)->after('mail_queued');
            $table->unsignedSmallInteger('mail_failed')->default(0)->after('mail_sent');
            $table->string('last_error', 120)->nullable()->after('mail_failed');
            $table->timestamp('processed_at')->nullable()->after('created_at');
            $table->timestamp('updated_at')->nullable()->after('processed_at');

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn(['status', 'event', 'subject_id', 'actor_id', 'attempts', 'mail_queued', 'mail_sent', 'mail_failed', 'last_error', 'processed_at', 'updated_at']);
        });
    }
};
