<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_value', 14, 2)->nullable();
            $table->decimal('max_discount_amount', 14, 2)->nullable();
            $table->decimal('min_purchase_amount', 14, 2)->nullable();

            $table->index(['discount_type']);
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex(['discount_type']);
            $table->dropColumn([
                'discount_type',
                'discount_value',
                'max_discount_amount',
                'min_purchase_amount',
            ]);
        });
    }
};
