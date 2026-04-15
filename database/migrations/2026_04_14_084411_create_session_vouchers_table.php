<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('session_vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('photo_sessions')->cascadeOnDelete();
            $table->string('voucher_code', 120);
            $table->string('voucher_type', 30)->default('promo');
            $table->string('status', 20)->default('applied');
            $table->foreignUuid('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('applied_at')->nullable();
            $table->foreignUuid('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('revoked_at')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata_json')->nullable();
            $table->timestampsTz();

            $table->index(['session_id', 'status']);
            $table->index(['voucher_code']);
            $table->index(['applied_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_vouchers');
    }
};
