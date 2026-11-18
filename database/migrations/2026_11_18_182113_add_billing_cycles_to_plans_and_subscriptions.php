<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single plan with three billing cycles (monthly, semiannual, annual), each with its own total price. The
     * promotion becomes a percentage valid for any cycle, and the AI allowance becomes a monthly credit franchise.
     * The subscription keeps the chosen cycle, a cycle change scheduled for the next period and the extra-user price
     * frozen at contracting. Each charge keeps the breakdown of its amount.
     *
     * The provisional prices of BILLING-01.1 (monthly R$ 139,90, semiannual R$ 719,40, annual R$ 1.318,80, three
     * users included, R$ 19,90 per extra user) are written only where no price was set yet, and stay marked as not
     * approved: while prices_approved_at is empty, no charge is issued in the production environment.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('semiannual_price_cents')->nullable()->after('price_cents');
            $table->unsignedInteger('annual_price_cents')->nullable()->after('semiannual_price_cents');
            $table->unsignedInteger('ai_monthly_credits')->nullable()->after('extra_user_price_cents');
            $table->unsignedTinyInteger('promotional_discount_percent')->default(0)->after('ai_monthly_credits');
            $table->timestamp('prices_approved_at')->nullable()->after('promotional_until');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['interval_months', 'ai_messages_per_month', 'promotional_price_cents']);
        });

        $provisional = [
            'price_cents' => 13990,
            'semiannual_price_cents' => 71940,
            'annual_price_cents' => 131880,
            'included_users' => 3,
            'extra_user_price_cents' => 1990,
        ];

        foreach ($provisional as $column => $value) {
            DB::table('plans')->where('code', 'principal')->whereNull($column)->update([$column => $value, 'updated_at' => now()]);
        }

        DB::table('plans')->where('code', 'principal')->where('name', 'VetorSmart')->update([
            'name' => 'VetorSmart Completo',
            'description' => 'Plano completo: todas as funcionalidades do painel, com ciclos mensal, semestral e anual.',
        ]);

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_interval', 20)->default('monthly')->after('payment_method');
            $table->string('scheduled_interval', 20)->nullable()->after('billing_interval');
            $table->unsignedInteger('extra_user_price_cents')->nullable()->after('price_cents');
        });

        Schema::table('billing_charges', function (Blueprint $table) {
            $table->string('billing_interval', 20)->default('monthly')->after('period_end');
            $table->unsignedInteger('base_amount_cents')->nullable()->after('amount_cents');
            $table->unsignedInteger('discount_cents')->default(0)->after('base_amount_cents');
            $table->unsignedSmallInteger('extra_users')->default(0)->after('discount_cents');
            $table->unsignedInteger('extra_users_amount_cents')->default(0)->after('extra_users');
        });
    }

    public function down(): void
    {
        Schema::table('billing_charges', function (Blueprint $table) {
            $table->dropColumn(['billing_interval', 'base_amount_cents', 'discount_cents', 'extra_users', 'extra_users_amount_cents']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['billing_interval', 'scheduled_interval', 'extra_user_price_cents']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedTinyInteger('interval_months')->default(1);
            $table->unsignedInteger('ai_messages_per_month')->nullable();
            $table->unsignedInteger('promotional_price_cents')->nullable();
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['semiannual_price_cents', 'annual_price_cents', 'ai_monthly_credits', 'promotional_discount_percent', 'prices_approved_at']);
        });
    }
};
