<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unique('cloud_account_id', 'customers_cloud_account_id_unique');
        });

        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->foreign('upgraded_from_id')
                ->references('id')
                ->on('customer_subscriptions')
                ->nullOnDelete();
        });

        DB::statement(
            "create unique index customer_subscriptions_customer_id_active_unique
            on customer_subscriptions (customer_id)
            where status = 'active'"
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists customer_subscriptions_customer_id_active_unique');

        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['upgraded_from_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_cloud_account_id_unique');
        });
    }
};
