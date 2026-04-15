<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('actor_type', 20)->nullable();
            $table->uuid('actor_id')->nullable();
            $table->string('entity_type', 30);
            $table->uuid('entity_id');
            $table->string('action', 50);
            $table->jsonb('before_json')->nullable();
            $table->jsonb('after_json')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->timestampsTz();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};