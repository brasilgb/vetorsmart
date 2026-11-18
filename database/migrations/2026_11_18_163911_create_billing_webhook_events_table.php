<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every gateway notice received. event_key is unique (Pix endToEndId, or Cobranças token plus event id), so a
     * notice delivered twice is recorded and processed once. The summary keeps identifiers and statuses only: no
     * payer document, no credential.
     */
    public function up(): void
    {
        Schema::create('billing_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('event_key', 191)->unique();
            $table->string('reference', 64)->nullable();
            $table->string('status', 20);
            $table->json('summary')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_events');
    }
};
