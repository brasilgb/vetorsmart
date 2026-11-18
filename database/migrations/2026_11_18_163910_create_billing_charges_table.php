<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One charge per subscription period. The unique (subscription, period) key makes a second request for the same
     * period return the existing charge instead of issuing another one. gateway_id (Pix txid or Cobranças charge_id)
     * is unique, so a payment notice always maps to a single local charge.
     */
    public function up(): void
    {
        Schema::create('billing_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->unsignedInteger('amount_cents');
            $table->date('due_date');
            $table->string('status', 20);
            $table->string('method', 20);
            $table->string('gateway', 20)->default('efi');
            $table->string('gateway_id', 64)->nullable()->unique();
            $table->text('pix_copy_paste')->nullable();
            $table->string('billet_url', 500)->nullable();
            $table->string('billet_pdf', 500)->nullable();
            $table->string('billet_barcode', 120)->nullable();
            $table->unsignedInteger('paid_amount_cents')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_via', 20)->nullable();
            $table->unsignedSmallInteger('issue_attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'period_start']);
            $table->index(['tenant_id', 'id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_charges');
    }
};
