<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->decimal('photobooth_price', 12, 2)->default(0);
            $table->decimal('additional_print_price', 12, 2)->default(0);
            $table->string('currency_code', 10)->default('IDR');

            $table->index(['status', 'currency_code']);
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropIndex(['status', 'currency_code']);
            $table->dropColumn([
                'photobooth_price',
                'additional_print_price',
                'currency_code',
            ]);
        });
    }
};
