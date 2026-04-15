<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('print_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('print_order_id')->constrained('print_orders')->cascadeOnDelete();
            $table->foreignUuid('rendered_output_id')->nullable()->constrained('rendered_outputs')->nullOnDelete();
            $table->foreignUuid('session_photo_id')->nullable()->constrained('session_photos')->nullOnDelete();
            $table->foreignUuid('file_id')->nullable()->constrained('asset_files')->nullOnDelete();
            $table->string('paper_size', 30)->nullable();
            $table->unsignedInteger('copies')->default(1);
            $table->string('print_layout', 50)->nullable();
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestampsTz();

            $table->index(['print_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_order_items');
    }
};