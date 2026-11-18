<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additional user seats become a contracted quantity, separate from the users registered. extra_seats is what the
     * company contracted beyond the included users; scheduled_extra_seats is a reduction waiting for the next period.
     *
     * Existing subscriptions start with the seats they already use beyond the included ones, so no company is blocked
     * and no amount changes compared with the previous rule (which charged the active users beyond the included).
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedSmallInteger('extra_seats')->default(0)->after('extra_user_price_cents');
            $table->unsignedSmallInteger('scheduled_extra_seats')->nullable()->after('extra_seats');
        });

        $included = DB::table('plans')->where('code', 'principal')->value('included_users');

        if ($included === null) {
            return;
        }

        DB::table('subscriptions')->orderBy('id')->each(function (object $subscription) use ($included): void {
            $active = DB::table('tenant_users')->where('tenant_id', $subscription->tenant_id)->where('status', 'active')->count();

            if ($active > $included) {
                DB::table('subscriptions')->where('id', $subscription->id)->update(['extra_seats' => $active - $included]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['extra_seats', 'scheduled_extra_seats']);
        });
    }
};
