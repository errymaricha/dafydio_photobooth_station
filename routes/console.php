<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment('Keep going');
})->purpose('Display an inspiring quote');

Schedule::command('print:fail-stuck-jobs')->everyMinute();
Schedule::command('printer:mark-offline')->everyMinute();