<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_daily_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('snapshot_date');
            $table->foreignUuid('station_id')
                ->constrained('stations')
                ->cascadeOnDelete();
            $table->string('currency_code', 10)->default('IDR');
            $table->decimal('revenue_amount', 16, 2)->default(0);
            $table->decimal('expense_amount', 16, 2)->default(0);
            $table->decimal('gross_profit_amount', 16, 2)->default(0);
            $table->decimal('net_profit_amount', 16, 2)->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->unsignedInteger('paid_session_count')->default(0);
            $table->timestampsTz();

            $table->unique(['snapshot_date', 'station_id', 'currency_code']);
            $table->index(['station_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_daily_snapshots');
    }
};
