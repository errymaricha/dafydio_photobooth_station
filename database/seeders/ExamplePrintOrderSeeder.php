<?php

namespace Database\Seeders;

use App\Models\PhotoSession;
use App\Models\Printer;
use App\Models\PrintLog;
use App\Models\PrintOrder;
use App\Models\PrintOrderItem;
use App\Models\PrintQueueJob;
use App\Models\RenderedOutput;
use App\Models\SessionPhoto;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExamplePrintOrderSeeder extends Seeder
{
    public function run(): void
    {
        $station = Station::query()
            ->where('station_code', 'STATION-01')
            ->first();
        $printer = Printer::query()
            ->where('printer_code', 'PRINTER-01')
            ->first();
        $admin = User::query()
            ->where('email', 'admin@photobooth.local')
            ->first();
        $session = PhotoSession::query()
            ->where('session_code', 'SES-DEMO-READY')
            ->first();

        if (! $station || ! $printer || ! $admin || ! $session) {
            return;
        }

        $renderedOutput = RenderedOutput::query()
            ->where('session_id', $session->id)
            ->where('is_active', true)
            ->first();
        $sessionPhoto = SessionPhoto::query()
            ->where('session_id', $session->id)
            ->orderBy('capture_index')
            ->first();

        if (! $renderedOutput || ! $sessionPhoto) {
            return;
        }

        $orderedAt = now()->subMinutes(15);
        $printOrder = PrintOrder::query()->firstOrNew([
            'order_code' => 'PO-EXAMPLE-001',
        ]);

        if (! $printOrder->exists) {
            $printOrder->id = (string) Str::uuid();
        }

        $printOrder->fill([
            'session_id' => $session->id,
            'user_id' => $admin->id,
            'station_id' => $station->id,
            'printer_id' => $printer->id,
            'source_type' => 'admin_panel',
            'order_type' => 'session_print',
            'payment_status' => 'unpaid',
            'total_items' => 2,
            'total_qty' => 3,
            'subtotal_amount' => 10000,
            'discount_amount' => 1000,
            'total_amount' => 9000,
            'status' => 'submitted',
            'ordered_at' => $orderedAt,
            'completed_at' => null,
        ]);
        $printOrder->save();

        $finalPrintItem = PrintOrderItem::query()->firstOrNew([
            'print_order_id' => $printOrder->id,
            'print_layout' => 'final_render',
        ]);

        if (! $finalPrintItem->exists) {
            $finalPrintItem->id = (string) Str::uuid();
        }

        $finalPrintItem->fill([
            'rendered_output_id' => $renderedOutput->id,
            'session_photo_id' => null,
            'file_id' => $renderedOutput->file_id,
            'paper_size' => '4R',
            'copies' => 1,
            'unit_price' => 5000,
            'line_total' => 5000,
            'status' => 'pending',
        ]);
        $finalPrintItem->save();

        $singlePhotoItem = PrintOrderItem::query()->firstOrNew([
            'print_order_id' => $printOrder->id,
            'print_layout' => 'single_photo',
        ]);

        if (! $singlePhotoItem->exists) {
            $singlePhotoItem->id = (string) Str::uuid();
        }

        $singlePhotoItem->fill([
            'rendered_output_id' => null,
            'session_photo_id' => $sessionPhoto->id,
            'file_id' => $sessionPhoto->original_file_id,
            'paper_size' => '4R',
            'copies' => 2,
            'unit_price' => 2500,
            'line_total' => 5000,
            'status' => 'pending',
        ]);
        $singlePhotoItem->save();

        $queueJob = PrintQueueJob::query()->firstOrNew([
            'print_order_id' => $printOrder->id,
        ]);

        if (! $queueJob->exists) {
            $queueJob->id = (string) Str::uuid();
        }

        $queueJob->fill([
            'printer_id' => $printer->id,
            'queue_name' => 'print',
            'priority' => 3,
            'job_payload' => [
                'order_code' => $printOrder->order_code,
                'items' => [
                    [
                        'layout' => 'final_render',
                        'copies' => 1,
                    ],
                    [
                        'layout' => 'single_photo',
                        'copies' => 2,
                    ],
                ],
            ],
            'attempt_count' => 0,
            'max_attempts' => 3,
            'status' => 'pending',
            'last_error' => null,
            'queued_at' => $orderedAt,
            'processed_at' => null,
            'finished_at' => null,
        ]);
        $queueJob->save();

        $printLog = PrintLog::query()->firstOrNew([
            'print_order_id' => $printOrder->id,
            'message' => 'Example order seeded for QA.',
        ]);

        if (! $printLog->exists) {
            $printLog->id = (string) Str::uuid();
        }

        $printLog->fill([
            'print_queue_job_id' => $queueJob->id,
            'printer_id' => $printer->id,
            'log_level' => 'info',
            'payload_json' => [
                'queue_status' => $queueJob->status,
                'seed_source' => 'example-print-order-seeder',
            ],
        ]);
        $printLog->save();
    }
}
