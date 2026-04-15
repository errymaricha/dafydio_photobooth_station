<?php

namespace Tests\Feature;

use App\Models\PhotoSession;
use App\Models\PrintOrder;
use App\Models\PrintQueueJob;
use App\Models\Printer;
use App\Models\Role;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrintAgentQueueWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_agent_only_receives_jobs_for_its_bound_printer(): void
    {
        $printerA = $this->createPrinter();
        $printerB = $this->createPrinter();
        $agent = $this->createPrintAgentUser($printerA);

        $jobForPrinterA = $this->createPendingJob($printerA);
        $this->createPendingJob($printerB, priority: 20);

        Sanctum::actingAs($agent);

        $this->getJson('/api/print-agent/jobs/next')
            ->assertOk()
            ->assertJsonPath('id', $jobForPrinterA->id)
            ->assertJsonPath('printer_id', $printerA->id);

        $this->getJson("/api/print-agent/jobs/next?printer_id={$printerB->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'This printer does not belong to the authenticated print-agent.');
    }

    public function test_print_agent_ack_updates_order_and_session_statuses(): void
    {
        $printer = $this->createPrinter();
        $agent = $this->createPrintAgentUser($printer);
        $job = $this->createPendingJob($printer);

        Sanctum::actingAs($agent);

        $this->postJson("/api/print-agent/jobs/{$job->id}/ack")
            ->assertOk()
            ->assertJsonPath('status', 'processing');

        $this->assertDatabaseHas('print_queue_jobs', [
            'id' => $job->id,
            'status' => 'processing',
            'attempt_count' => 1,
        ]);

        $this->assertDatabaseHas('print_orders', [
            'id' => $job->print_order_id,
            'status' => 'printing',
        ]);

        $this->assertDatabaseHas('photo_sessions', [
            'id' => $job->printOrder?->session_id,
            'status' => 'printing',
        ]);
    }

    public function test_print_agent_cannot_update_a_job_for_another_printer(): void
    {
        $printerA = $this->createPrinter();
        $printerB = $this->createPrinter();
        $agent = $this->createPrintAgentUser($printerA);
        $job = $this->createPendingJob($printerB);

        Sanctum::actingAs($agent);

        $this->postJson("/api/print-agent/jobs/{$job->id}/ack")
            ->assertForbidden()
            ->assertJsonPath('message', 'This job does not belong to this printer.');
    }

    protected function createPrintAgentUser(Printer $printer): User
    {
        $user = User::factory()->create([
            'printer_id' => $printer->id,
        ]);

        $role = Role::create([
            'code' => 'print-agent',
            'name' => 'Print Agent',
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }

    protected function createPendingJob(Printer $printer, int $priority = 5): PrintQueueJob
    {
        $session = $this->createPhotoSession($printer->station_id);
        $order = PrintOrder::create([
            'id' => (string) Str::uuid(),
            'order_code' => 'PO-' . Str::upper(Str::random(8)),
            'session_id' => $session->id,
            'station_id' => $printer->station_id,
            'printer_id' => $printer->id,
            'source_type' => 'admin_panel',
            'order_type' => 'session_print',
            'payment_status' => 'unpaid',
            'total_items' => 1,
            'total_qty' => 1,
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 0,
            'status' => 'queued',
            'ordered_at' => now(),
        ]);

        return PrintQueueJob::create([
            'id' => (string) Str::uuid(),
            'print_order_id' => $order->id,
            'printer_id' => $printer->id,
            'queue_name' => 'print',
            'priority' => $priority,
            'job_payload' => [
                'order_code' => $order->order_code,
            ],
            'attempt_count' => 0,
            'max_attempts' => 3,
            'status' => 'pending',
            'queued_at' => now(),
        ]);
    }

    protected function createPrinter(): Printer
    {
        $station = Station::create([
            'station_code' => 'ST-' . Str::upper(Str::random(6)),
            'station_name' => 'Station ' . Str::upper(Str::random(4)),
            'location_name' => 'Studio',
            'timezone' => 'Asia/Jakarta',
            'status' => 'online',
        ]);

        return Printer::create([
            'id' => (string) Str::uuid(),
            'station_id' => $station->id,
            'printer_code' => 'PR-' . Str::upper(Str::random(6)),
            'printer_name' => 'Printer ' . Str::upper(Str::random(4)),
            'printer_type' => 'inkjet',
            'connection_type' => 'network',
            'ip_address' => '192.168.1.10',
            'port' => 9100,
            'paper_size_default' => '4R',
            'status' => 'ready',
            'is_default' => false,
            'last_seen_at' => now(),
        ]);
    }

    protected function createPhotoSession(string $stationId): PhotoSession
    {
        return PhotoSession::create([
            'id' => (string) Str::uuid(),
            'session_code' => 'SES-' . Str::upper(Str::random(8)),
            'station_id' => $stationId,
            'session_type' => 'photobooth',
            'source_type' => 'android',
            'captured_count' => 1,
            'status' => 'queued_print',
            'captured_at' => now(),
        ]);
    }
}
