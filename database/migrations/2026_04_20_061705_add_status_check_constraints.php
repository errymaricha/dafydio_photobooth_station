<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "alter table customers
            add constraint customers_tier_check
            check (tier in ('regular', 'premium'))"
        );

        DB::statement(
            "alter table customers
            add constraint customers_status_check
            check (status in ('active', 'inactive'))"
        );

        DB::statement(
            "alter table customer_subscriptions
            add constraint customer_subscriptions_status_check
            check (status in ('active', 'upgraded', 'cancelled'))"
        );

        DB::statement(
            "alter table photo_sessions
            add constraint photo_sessions_status_check
            check (status in ('draft', 'created', 'uploaded', 'editing', 'ready_print', 'queued_print', 'failed_print', 'printed', 'completed'))"
        );

        DB::statement(
            "alter table photo_sessions
            add constraint photo_sessions_payment_status_check
            check (payment_status in ('pending', 'paid'))"
        );

        DB::statement(
            "alter table photo_sessions
            add constraint photo_sessions_payment_method_check
            check (payment_method is null or payment_method in ('manual', 'qris', 'cash', 'voucher'))"
        );

        DB::statement(
            "alter table photo_sessions
            add constraint photo_sessions_manual_payment_status_check
            check (manual_payment_status is null or manual_payment_status in ('pending_approval', 'approved', 'rejected'))"
        );

        DB::statement(
            "alter table photo_sessions
            add constraint photo_sessions_manual_payment_consistency_check
            check (
                (payment_method = 'manual' and manual_payment_status in ('pending_approval', 'approved', 'rejected'))
                or (payment_method <> 'manual' and manual_payment_status is null)
                or (payment_method is null and manual_payment_status is null)
            )"
        );
    }

    public function down(): void
    {
        DB::statement('alter table photo_sessions drop constraint if exists photo_sessions_manual_payment_consistency_check');
        DB::statement('alter table photo_sessions drop constraint if exists photo_sessions_manual_payment_status_check');
        DB::statement('alter table photo_sessions drop constraint if exists photo_sessions_payment_method_check');
        DB::statement('alter table photo_sessions drop constraint if exists photo_sessions_payment_status_check');
        DB::statement('alter table photo_sessions drop constraint if exists photo_sessions_status_check');
        DB::statement('alter table customer_subscriptions drop constraint if exists customer_subscriptions_status_check');
        DB::statement('alter table customers drop constraint if exists customers_status_check');
        DB::statement('alter table customers drop constraint if exists customers_tier_check');
    }
};
