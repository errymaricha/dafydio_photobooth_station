<?php

namespace App\Console\Commands;

use App\Models\Printer;
use Illuminate\Console\Command;

class MarkOfflinePrinters extends Command
{
    protected $signature = 'printer:mark-offline';
    protected $description = 'Mark stale printers as offline';

    public function handle(): int
    {
        $count = Printer::whereNotNull('last_seen_at')
            ->where('last_seen_at', '<=', now()->subMinutes(2))
            ->where('status', '!=', 'offline')
            ->update([
                'status' => 'offline',
            ]);

        $this->info("Printers marked offline: {$count}");

        return self::SUCCESS;
    }
}