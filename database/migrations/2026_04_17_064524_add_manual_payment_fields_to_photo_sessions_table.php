<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_sessions', function (Blueprint $table) {
            $table->string('customer_whatsapp', 30)->nullable();
            $table->unsignedInteger('additional_print_count')->default(0);
            $table->string('manual_payment_status', 30)->nullable();
            $table->timestampTz('manual_payment_reviewed_at')->nullable();
            $table->foreignUuid('manual_payment_reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('manual_payment_notes')->nullable();

            $table->index(['payment_method', 'manual_payment_status']);
            $table->index(['customer_whatsapp']);
        });
    }

    public function down(): void
    {
        Schema::table('photo_sessions', function (Blueprint $table) {
            $table->dropIndex(['payment_method', 'manual_payment_status']);
            $table->dropIndex(['customer_whatsapp']);
            $table->dropConstrainedForeignId('manual_payment_reviewed_by');
            $table->dropColumn([
                'customer_whatsapp',
                'additional_print_count',
                'manual_payment_status',
                'manual_payment_reviewed_at',
                'manual_payment_notes',
            ]);
        });
    }
};
