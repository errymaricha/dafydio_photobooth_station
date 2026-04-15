<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_sessions', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('pending');
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_ref', 100)->nullable();
            $table->timestampTz('paid_at')->nullable();

            $table->index(['payment_status', 'created_at']);
            $table->index(['payment_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('photo_sessions', function (Blueprint $table) {
            $table->dropIndex(['payment_status', 'created_at']);
            $table->dropIndex(['payment_ref']);
            $table->dropColumn([
                'payment_status',
                'payment_method',
                'payment_ref',
                'paid_at',
            ]);
        });
    }
};
