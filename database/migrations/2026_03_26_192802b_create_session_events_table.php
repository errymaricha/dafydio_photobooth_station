<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('session_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('photo_sessions')->cascadeOnDelete();
            $table->string('event_type', 50);
            $table->string('actor_type', 20)->nullable();
            $table->uuid('actor_id')->nullable();
            $table->jsonb('payload_json')->nullable();
            $table->timestampsTz();

            $table->index(['session_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_events');
    }
};