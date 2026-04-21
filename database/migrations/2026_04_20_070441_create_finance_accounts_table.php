<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('account_code', 30)->unique();
            $table->string('account_name', 120);
            $table->string('account_type', 20);
            $table->string('normal_balance', 10);
            $table->uuid('parent_id')->nullable();
            $table->unsignedSmallInteger('level_no')->default(1);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignUuid('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampsTz();

            $table->index(['account_type', 'is_active']);
            $table->index(['parent_id', 'is_active']);
        });

        Schema::table('finance_accounts', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('finance_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_accounts', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });

        Schema::dropIfExists('finance_accounts');
    }
};
