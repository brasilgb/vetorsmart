<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI credits of each company, kept in two buckets: the monthly franchise of the plan (renewed every month, the
     * leftover expires) and purchased credits (never expire). ai_credit_accounts holds the balances, changed only under
     * a row lock; ai_credit_entries is the append-only ledger, with a unique reference per operation so a consumption,
     * a grant or a purchase is never applied twice. Purchases are separate, auditable records with their own status.
     * No package is created here: package sizes and prices are commercial decisions of the RootAdmin.
     */
    public function up(): void
    {
        Schema::create('ai_credit_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->integer('included_balance')->default(0);
            $table->integer('purchased_balance')->default(0);
            $table->string('included_period', 7)->nullable();
            $table->string('low_balance_alerted_period', 7)->nullable();
            $table->string('depleted_alerted_period', 7)->nullable();
            $table->timestamps();
        });

        Schema::create('ai_credit_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->unsignedInteger('credits');
            $table->unsignedInteger('price_cents');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_credit_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('ai_credit_packages')->nullOnDelete();
            $table->string('package_name', 80);
            $table->unsignedInteger('credits');
            $table->unsignedInteger('price_cents');
            $table->string('status', 30);
            $table->string('idempotency_key', 120)->unique();
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_via', 20)->nullable();
            $table->unsignedInteger('refunded_credits')->default(0);
            $table->unsignedInteger('refunded_cents')->default(0);
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'id']);
            $table->index('status');
        });

        Schema::create('ai_credit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('reference', 150)->unique();
            $table->integer('included_delta')->default(0);
            $table->integer('purchased_delta')->default(0);
            $table->unsignedInteger('credits')->default(0);
            $table->unsignedInteger('uncovered_credits')->default(0);
            $table->string('operation', 60)->nullable();
            $table->string('model', 60)->nullable();
            $table->unsignedInteger('tokens')->nullable();
            $table->unsignedBigInteger('estimated_cost_micros')->nullable();
            $table->foreignId('purchase_id')->nullable()->constrained('ai_credit_purchases')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'id']);
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->unsignedInteger('ai_tokens_per_credit')->default(1000)->after('billet_due_days');
            $table->unsignedTinyInteger('ai_low_balance_percent')->default(20)->after('ai_tokens_per_credit');
            $table->boolean('ai_credit_purchases_enabled')->default(false)->after('ai_low_balance_percent');
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn(['ai_tokens_per_credit', 'ai_low_balance_percent', 'ai_credit_purchases_enabled']);
        });

        Schema::dropIfExists('ai_credit_entries');
        Schema::dropIfExists('ai_credit_purchases');
        Schema::dropIfExists('ai_credit_packages');
        Schema::dropIfExists('ai_credit_accounts');
    }
};
