<?php

namespace Database\Seeders;

use App\Models\AndroidDevice;
use App\Models\AssetFile;
use App\Models\EditJob;
use App\Models\EditJobItem;
use App\Models\PhotoSession;
use App\Models\PrintLog;
use App\Models\PrintOrder;
use App\Models\PrintOrderItem;
use App\Models\PrintQueueJob;
use App\Models\Printer;
use App\Models\RenderedOutput;
use App\Models\SessionPhoto;
use App\Models\Station;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $station = Station::query()
            ->where('station_code', 'STATION-01')
            ->first();

        $template = Template::query()
            ->where('template_code', 'TPL-2SLOT')
            ->first();

        $printer = Printer::query()
            ->where('printer_code', 'PRINTER-01')
            ->first();

        $admin = User::query()
            ->where('email', 'admin@photobooth.local')
            ->first();

        if (! $station || ! $template || ! $printer || ! $admin) {
            return;
        }

        $device = AndroidDevice::query()
            ->where('device_code', 'PB-DEVICE-01')
            ->first();

        $printer->fill([
            'status' => 'ready',
            'last_seen_at' => now(),
            'last_error' => null,
            'meta_json' => [
                'seed_source' => 'demo-workflow-seeder',
            ],
        ]);
        $printer->save();

        $scenarios = [
            [
                'session_code' => 'SES-DEMO-UPLOADED',
                'status' => 'uploaded',
                'captured_at' => now()->subHours(6),
                'completed_at' => now()->subHours(5),
                'color' => '#2563eb',
            ],
            [
                'session_code' => 'SES-DEMO-EDITING',
                'status' => 'editing',
                'captured_at' => now()->subHours(4),
                'completed_at' => null,
                'edit_job_status' => 'draft',
                'color' => '#16a34a',
            ],
            [
                'session_code' => 'SES-DEMO-READY',
                'status' => 'ready_print',
                'captured_at' => now()->subHours(3),
                'completed_at' => now()->subHours(2),
                'edit_job_status' => 'completed',
                'rendered' => true,
                'color' => '#f97316',
            ],
            [
                'session_code' => 'SES-DEMO-QUEUED',
                'status' => 'queued_print',
                'captured_at' => now()->subHours(2),
                'completed_at' => now()->subMinutes(100),
                'edit_job_status' => 'completed',
                'rendered' => true,
                'order' => [
                    'order_code' => 'PO-DEMO-QUEUED',
                    'status' => 'queued',
                    'ordered_at' => now()->subMinutes(90),
                    'completed_at' => null,
                    'queue_status' => 'pending',
                    'queue_error' => null,
                    'log_level' => 'info',
                    'log_message' => 'Queued by demo seeder',
                ],
                'color' => '#0ea5e9',
            ],
            [
                'session_code' => 'SES-DEMO-FAILED',
                'status' => 'failed_print',
                'captured_at' => now()->subMinutes(80),
                'completed_at' => now()->subMinutes(70),
                'edit_job_status' => 'completed',
                'rendered' => true,
                'order' => [
                    'order_code' => 'PO-DEMO-FAILED',
                    'status' => 'failed',
                    'ordered_at' => now()->subMinutes(60),
                    'completed_at' => null,
                    'queue_status' => 'failed',
                    'queue_error' => 'Paper jam during printing.',
                    'log_level' => 'error',
                    'log_message' => 'Printer reported paper jam.',
                ],
                'color' => '#dc2626',
            ],
            [
                'session_code' => 'SES-DEMO-PRINTED',
                'status' => 'printed',
                'captured_at' => now()->subMinutes(50),
                'completed_at' => now()->subMinutes(40),
                'edit_job_status' => 'completed',
                'rendered' => true,
                'order' => [
                    'order_code' => 'PO-DEMO-PRINTED',
                    'status' => 'printed',
                    'ordered_at' => now()->subMinutes(30),
                    'completed_at' => now()->subMinutes(20),
                    'queue_status' => 'completed',
                    'queue_error' => null,
                    'log_level' => 'info',
                    'log_message' => 'Printed successfully.',
                ],
                'color' => '#7c3aed',
            ],
        ];

        foreach ($scenarios as $scenario) {
            DB::transaction(function () use ($admin, $device, $printer, $scenario, $station, $template): void {
                $this->seedSessionScenario(
                    station: $station,
                    device: $device,
                    admin: $admin,
                    template: $template,
                    printer: $printer,
                    scenario: $scenario,
                );
            });
        }
    }

    /**
     * @param  array{
     *   session_code: string,
     *   status: string,
     *   captured_at: Carbon,
     *   completed_at: ?Carbon,
     *   color: string,
     *   edit_job_status?: string,
     *   rendered?: bool,
     *   order?: array{
     *     order_code: string,
     *     status: string,
     *     ordered_at: Carbon,
     *     completed_at: ?Carbon,
     *     queue_status: string,
     *     queue_error: ?string,
     *     log_level: string,
     *     log_message: string
     *   }
     * }  $scenario
     */
    private function seedSessionScenario(
        Station $station,
        ?AndroidDevice $device,
        User $admin,
        Template $template,
        Printer $printer,
        array $scenario,
    ): void {
        $session = PhotoSession::firstOrNew([
            'session_code' => $scenario['session_code'],
        ]);

        if (! $session->exists) {
            $session->id = (string) Str::uuid();
        }

        $session->fill([
            'station_id' => $station->id,
            'device_id' => $device?->id,
            'user_id' => $admin->id,
            'template_id' => $template->id,
            'session_type' => 'photobooth',
            'source_type' => 'android',
            'total_expected_photos' => 2,
            'captured_count' => 2,
            'status' => $scenario['status'],
            'captured_at' => $scenario['captured_at'],
            'completed_at' => $scenario['completed_at'],
            'notes' => 'Demo workflow data',
        ]);
        $session->save();

        $photos = [];
        for ($captureIndex = 1; $captureIndex <= 2; $captureIndex++) {
            $photoPrefix = strtolower($session->session_code);
            $originalPath = "demo/sessions/{$photoPrefix}/original/PB-D_0{$captureIndex}.svg";
            $thumbPath = "demo/sessions/{$photoPrefix}/thumbs/TH_0{$captureIndex}.svg";

            $originalFile = $this->upsertSvgAsset(
                relativePath: $originalPath,
                label: "{$session->session_code} Photo {$captureIndex}",
                accentColor: $scenario['color'],
                width: 1200,
                height: 900,
                category: 'session_photo',
                createdBy: $admin,
            );

            $thumbFile = $this->upsertSvgAsset(
                relativePath: $thumbPath,
                label: "{$session->session_code} Thumb {$captureIndex}",
                accentColor: $scenario['color'],
                width: 600,
                height: 450,
                category: 'session_thumb',
                createdBy: $admin,
            );

            $photo = SessionPhoto::firstOrNew([
                'session_id' => $session->id,
                'capture_index' => $captureIndex,
            ]);

            if (! $photo->exists) {
                $photo->id = (string) Str::uuid();
            }

            $photo->fill([
                'slot_index' => $captureIndex,
                'original_file_id' => $originalFile->id,
                'thumbnail_file_id' => $thumbFile->id,
                'composed_preview_file_id' => $thumbFile->id,
                'checksum_sha256' => $originalFile->checksum_sha256,
                'width' => 1200,
                'height' => 900,
                'file_size_bytes' => $originalFile->file_size_bytes,
                'mime_type' => 'image/svg+xml',
                'is_selected' => true,
                'uploaded_at' => $scenario['captured_at'],
            ]);
            $photo->save();

            $photos[] = $photo;
        }

        $editJobStatus = $scenario['edit_job_status'] ?? null;
        if (! $editJobStatus) {
            return;
        }

        $editJob = EditJob::firstOrNew([
            'session_id' => $session->id,
            'version_no' => 1,
        ]);

        if (! $editJob->exists) {
            $editJob->id = (string) Str::uuid();
        }

        $editJob->fill([
            'editor_id' => $admin->id,
            'template_id' => $template->id,
            'edit_state_json' => [
                'template_id' => $template->id,
                'source' => 'demo_seed',
            ],
            'status' => $editJobStatus,
            'started_at' => $scenario['captured_at']->copy()->addMinutes(2),
            'completed_at' => $editJobStatus === 'completed'
                ? ($scenario['completed_at']?->copy()->subMinutes(2))
                : null,
        ]);
        $editJob->save();

        foreach ($photos as $photo) {
            $item = EditJobItem::firstOrNew([
                'edit_job_id' => $editJob->id,
                'slot_index' => $photo->capture_index,
            ]);

            if (! $item->exists) {
                $item->id = (string) Str::uuid();
            }

            $item->fill([
                'session_photo_id' => $photo->id,
                'crop_json' => [
                    'zoom' => 1,
                    'offset_x' => 0,
                    'offset_y' => 0,
                ],
                'transform_json' => [
                    'rotation' => 0,
                ],
                'filter_json' => [
                    'preset' => 'normal',
                ],
            ]);
            $item->save();
        }

        if (! ($scenario['rendered'] ?? false)) {
            return;
        }

        $renderPath = 'demo/sessions/' . strtolower($session->session_code) . '/rendered/FINAL_V1.svg';
        $renderFile = $this->upsertSvgAsset(
            relativePath: $renderPath,
            label: "{$session->session_code} Final Output",
            accentColor: $scenario['color'],
            width: 1200,
            height: 1800,
            category: 'rendered_output',
            createdBy: $admin,
        );

        RenderedOutput::query()
            ->where('session_id', $session->id)
            ->update([
                'is_active' => false,
            ]);

        $renderedOutput = RenderedOutput::firstOrNew([
            'session_id' => $session->id,
            'version_no' => 1,
        ]);

        if (! $renderedOutput->exists) {
            $renderedOutput->id = (string) Str::uuid();
        }

        $renderedOutput->fill([
            'edit_job_id' => $editJob->id,
            'file_id' => $renderFile->id,
            'render_type' => 'final_print',
            'width' => 1200,
            'height' => 1800,
            'dpi' => 300,
            'is_active' => true,
            'rendered_at' => $scenario['completed_at'] ?? now(),
        ]);
        $renderedOutput->save();

        $orderConfig = $scenario['order'] ?? null;
        if (! $orderConfig) {
            return;
        }

        $printOrder = PrintOrder::firstOrNew([
            'order_code' => $orderConfig['order_code'],
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
            'payment_status' => 'paid',
            'total_items' => 1,
            'total_qty' => 2,
            'subtotal_amount' => 5000,
            'discount_amount' => 0,
            'total_amount' => 5000,
            'status' => $orderConfig['status'],
            'ordered_at' => $orderConfig['ordered_at'],
            'completed_at' => $orderConfig['completed_at'],
        ]);
        $printOrder->save();

        $printOrderItem = PrintOrderItem::firstOrNew([
            'print_order_id' => $printOrder->id,
            'rendered_output_id' => $renderedOutput->id,
        ]);

        if (! $printOrderItem->exists) {
            $printOrderItem->id = (string) Str::uuid();
        }

        $printOrderItem->fill([
            'session_photo_id' => null,
            'file_id' => $renderFile->id,
            'paper_size' => '4R',
            'copies' => 2,
            'print_layout' => 'single',
            'unit_price' => 2500,
            'line_total' => 5000,
            'status' => $orderConfig['status'],
        ]);
        $printOrderItem->save();

        $queueJob = PrintQueueJob::firstOrNew([
            'print_order_id' => $printOrder->id,
        ]);

        if (! $queueJob->exists) {
            $queueJob->id = (string) Str::uuid();
        }

        $queueJob->fill([
            'printer_id' => $printer->id,
            'queue_name' => 'print',
            'priority' => $orderConfig['status'] === 'failed' ? 10 : 1,
            'job_payload' => [
                'order_code' => $printOrder->order_code,
                'file_path' => $renderFile->file_path,
                'copies' => 2,
            ],
            'attempt_count' => $orderConfig['status'] === 'failed' ? 2 : 1,
            'max_attempts' => 3,
            'status' => $orderConfig['queue_status'],
            'last_error' => $orderConfig['queue_error'],
            'queued_at' => $orderConfig['ordered_at'],
            'processed_at' => in_array($orderConfig['queue_status'], ['failed', 'completed'], true)
                ? $orderConfig['ordered_at']->copy()->addMinutes(1)
                : null,
            'finished_at' => in_array($orderConfig['queue_status'], ['failed', 'completed'], true)
                ? ($orderConfig['completed_at'] ?? $orderConfig['ordered_at']->copy()->addMinutes(2))
                : null,
        ]);
        $queueJob->save();

        $printLog = PrintLog::firstOrNew([
            'print_order_id' => $printOrder->id,
            'message' => $orderConfig['log_message'],
        ]);

        if (! $printLog->exists) {
            $printLog->id = (string) Str::uuid();
        }

        $printLog->fill([
            'print_queue_job_id' => $queueJob->id,
            'printer_id' => $printer->id,
            'log_level' => $orderConfig['log_level'],
            'payload_json' => [
                'queue_status' => $queueJob->status,
                'error' => $queueJob->last_error,
            ],
        ]);
        $printLog->save();
    }

    private function upsertSvgAsset(
        string $relativePath,
        string $label,
        string $accentColor,
        int $width,
        int $height,
        string $category,
        User $createdBy,
    ): AssetFile {
        $content = $this->buildSvgPlaceholder(
            label: $label,
            accentColor: $accentColor,
            width: $width,
            height: $height,
        );

        Storage::disk('public')->put($relativePath, $content);

        $asset = AssetFile::firstOrNew([
            'file_path' => $relativePath,
        ]);

        if (! $asset->exists) {
            $asset->id = (string) Str::uuid();
        }

        $asset->fill([
            'storage_disk' => 'public',
            'file_name' => basename($relativePath),
            'file_ext' => 'svg',
            'mime_type' => 'image/svg+xml',
            'file_size_bytes' => strlen($content),
            'checksum_sha256' => hash('sha256', $content),
            'width' => $width,
            'height' => $height,
            'file_category' => $category,
            'created_by_type' => 'user',
            'created_by_id' => $createdBy->id,
        ]);
        $asset->save();

        return $asset;
    }

    private function buildSvgPlaceholder(
        string $label,
        string $accentColor,
        int $width,
        int $height,
    ): string {
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}">
    <rect x="0" y="0" width="{$width}" height="{$height}" fill="#111827"/>
    <rect x="24" y="24" width="{$width}" height="{$height}" fill="{$accentColor}" opacity="0.88"/>
    <rect x="64" y="64" width="{$width}" height="{$height}" fill="#0f172a" opacity="0.22"/>
    <text x="72" y="120" fill="#ffffff" font-size="46" font-family="Arial, sans-serif" font-weight="700">Photobooth Demo</text>
    <text x="72" y="176" fill="#f8fafc" font-size="30" font-family="Arial, sans-serif">{$escapedLabel}</text>
</svg>
SVG;
    }
}
