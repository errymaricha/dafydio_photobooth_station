<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usage_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('session_id')->nullable()->constrained('photo_sessions')->nullOnDelete();
            $table->foreignUuid('print_order_id')->nullable()->constrained('print_orders')->nullOnDelete();
            $table->string('activity_type', 50);
            $table->text('description')->nullable();
            $table->jsonb('metadata_json')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_histories');
    }
};