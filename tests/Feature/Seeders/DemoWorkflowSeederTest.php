<?php

namespace Tests\Feature\Seeders;

use App\Models\PhotoSession;
use App\Models\PrintOrder;
use App\Models\SessionPhoto;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoWorkflowSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_workflow_seeder_populates_visual_data_and_stays_idempotent(): void
    {
        $sessionCodes = [
            'SES-DEMO-UPLOADED',
            'SES-DEMO-EDITING',
            'SES-DEMO-READY',
            'SES-DEMO-QUEUED',
            'SES-DEMO-FAILED',
            'SES-DEMO-PRINTED',
        ];
        $orderCodes = [
            'PO-DEMO-QUEUED',
            'PO-DEMO-FAILED',
            'PO-DEMO-PRINTED',
            'PO-EXAMPLE-001',
        ];

        $this->seed(DatabaseSeeder::class);

        $firstSessionIds = PhotoSession::query()
            ->whereIn('session_code', $sessionCodes)
            ->pluck('id', 'session_code')
            ->all();

        $firstOrderIds = PrintOrder::query()
            ->whereIn('order_code', $orderCodes)
            ->pluck('id', 'order_code')
            ->all();

        ksort($firstSessionIds);
        ksort($firstOrderIds);

        $this->assertCount(6, $firstSessionIds);
        $this->assertCount(4, $firstOrderIds);

        $this->assertDatabaseCount('photo_sessions', 6);
        $this->assertDatabaseCount('session_photos', 12);
        $this->assertDatabaseCount('edit_jobs', 5);
        $this->assertDatabaseCount('rendered_outputs', 4);
        $this->assertDatabaseCount('print_orders', 4);
        $this->assertDatabaseCount('print_order_items', 5);
        $this->assertDatabaseCount('print_queue_jobs', 4);
        $this->assertDatabaseCount('print_logs', 4);
        $this->assertDatabaseCount('asset_files', 28);

        foreach ($sessionCodes as $sessionCode) {
            $sessionId = $firstSessionIds[$sessionCode] ?? null;
            $this->assertNotNull($sessionId);
            $this->assertSame(
                2,
                SessionPhoto::query()->where('session_id', $sessionId)->count(),
            );
        }

        $this->assertDatabaseHas('photo_sessions', [
            'session_code' => 'SES-DEMO-READY',
            'status' => 'ready_print',
        ]);
        $this->assertDatabaseHas('photo_sessions', [
            'session_code' => 'SES-DEMO-FAILED',
            'status' => 'failed_print',
        ]);
        $this->assertDatabaseHas('print_orders', [
            'order_code' => 'PO-DEMO-PRINTED',
            'status' => 'printed',
        ]);
        $this->assertDatabaseHas('print_orders', [
            'order_code' => 'PO-EXAMPLE-001',
            'status' => 'submitted',
            'payment_status' => 'unpaid',
        ]);
        $this->assertDatabaseHas('print_queue_jobs', [
            'status' => 'failed',
            'last_error' => 'Paper jam during printing.',
        ]);

        $this->seed(DatabaseSeeder::class);

        $secondSessionIds = PhotoSession::query()
            ->whereIn('session_code', $sessionCodes)
            ->pluck('id', 'session_code')
            ->all();

        $secondOrderIds = PrintOrder::query()
            ->whereIn('order_code', $orderCodes)
            ->pluck('id', 'order_code')
            ->all();

        ksort($secondSessionIds);
        ksort($secondOrderIds);

        $this->assertSame($firstSessionIds, $secondSessionIds);
        $this->assertSame($firstOrderIds, $secondOrderIds);
        $this->assertDatabaseCount('photo_sessions', 6);
        $this->assertDatabaseCount('session_photos', 12);
        $this->assertDatabaseCount('print_orders', 4);
        $this->assertDatabaseCount('print_order_items', 5);
        $this->assertDatabaseCount('print_queue_jobs', 4);
        $this->assertDatabaseCount('print_logs', 4);
    }
}
