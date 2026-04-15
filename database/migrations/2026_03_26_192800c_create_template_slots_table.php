<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('template_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('templates')->cascadeOnDelete();
            $table->unsignedInteger('slot_index');
            $table->integer('x')->default(0);
            $table->integer('y')->default(0);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->decimal('rotation', 8, 2)->default(0);
            $table->unsignedInteger('border_radius')->default(0);
            $table->jsonb('metadata_json')->nullable();
            $table->timestampsTz();

            $table->unique(['template_id', 'slot_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_slots');
    }
};