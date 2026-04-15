<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('public_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('print_order_id')->constrained('print_orders')->cascadeOnDelete();
            $table->foreignUuid('pickup_station_id')->constrained('stations')->restrictOnDelete();
            $table->string('contact_name', 150);
            $table->string('contact_phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->string('fulfillment_status', 20)->default('new');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_orders');
    }
};