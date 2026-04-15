<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('print_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_code', 60)->unique();
            $table->foreignUuid('session_id')->nullable()->constrained('photo_sessions')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('station_id')->constrained('stations')->restrictOnDelete();
            $table->foreignUuid('printer_id')->nullable()->constrained('printers')->nullOnDelete();
            $table->string('source_type', 20)->default('local_station');
            $table->string('order_type', 20)->default('session_print');
            $table->string('payment_status', 20)->default('unpaid');
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('total_qty')->default(0);
            $table->decimal('subtotal_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('status', 25)->default('submitted');
            $table->timestampTz('ordered_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['station_id', 'status', 'ordered_at']);
            $table->index(['user_id', 'ordered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_orders');
    }
};