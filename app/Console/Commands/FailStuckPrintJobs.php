<?php

namespace App\Console\Commands;

use App\Models\PrintLog;
use App\Models\PrintQueueJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FailStuckPrintJobs extends Command
{
    protected $signature = 'print:fail-stuck-jobs';
    protected $description = 'Fail stuck print jobs that have been processing too long';

    public function handle(): int
    {
        $jobs = PrintQueueJob::with('printOrder.session')
            ->where('status', 'processing')
            ->where('processed_at', '<', now()->subMinutes(5))
            ->get();

        foreach ($jobs as $job) {
            DB::transaction(function () use ($job) {
                $job->update([
                    'status' => 'failed',
                    'last_error' => 'Timeout (auto fail)',
                    'finished_at' => now(),
                ]);

                $job->printOrder?->update([
                    'status' => 'failed',
                ]);

                if ($job->printOrder?->session) {
                    $job->printOrder->session->update([
                        'status' => 'failed_print',
                    ]);
                }

                PrintLog::create([
                    'id' => (string) Str::uuid(),
                    'print_order_id' => $job->print_order_id,
                    'print_queue_job_id' => $job->id,
                    'printer_id' => $job->printer_id,
                    'log_level' => 'error',
                    'message' => 'Print job auto-failed due to timeout',
                    'payload_json' => [
                        'reason' => 'processing_timeout',
                    ],
                ]);
            });

            $this->info("Failed stuck job: {$job->id}");
        }

        return self::SUCCESS;
    }
}