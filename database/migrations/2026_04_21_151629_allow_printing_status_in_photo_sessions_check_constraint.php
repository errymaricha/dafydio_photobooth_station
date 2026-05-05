<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table photo_sessions drop constraint if exists photo_sessions_status_check');
        DB::statement(
            "alter table photo_sessions
            add constraint photo_sessions_status_check
            check (status in ('draft', 'created', 'uploaded', 'editing', 'ready_print', 'queued_print', 'printing', 'failed_print', 'printed', 'completed'))"
        );
    }

    public function down(): void
    {
        DB::statement('alter table photo_sessions drop constraint if exists photo_sessions_status_check');
        DB::statement(
            "alter table photo_sessions
            add constraint photo_sessions_status_check
            check (status in ('draft', 'created', 'uploaded', 'editing', 'ready_print', 'queued_print', 'failed_print', 'printed', 'completed'))"
        );
    }
};
