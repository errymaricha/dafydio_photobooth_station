<?php

namespace Tests\Feature;

use App\Models\AndroidDevice;
use App\Models\AssetFile;
use App\Models\EditJob;
use App\Models\PhotoSession;
use App\Models\Role;
use App\Models\SessionPhoto;
use App\Models\SessionVoucher;
use App\Models\Station;
use App\Models\Template;
use App\Models\TemplateAsset;
use App\Models\TemplateSlot;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Queue::fake();
    }

    public function test_device_login_rotates_existing_tokens(): void
    {
        $device = $this->createDevice();
        $device->createToken('old-token');

        $this->postJson('/api/device/auth', [
            'device_code' => $device->device_code,
            'api_key' => 'top-secret-device-key',
        ])
            ->assertOk()
            ->assertJsonPath('device_id', $device->id)
            ->assertJsonPath('device_code', $device->device_code);

        $this->assertCount(1, $device->fresh()->tokens);
    }

    public function test_device_cannot_upload_photo_to_another_device_session(): void
    {
        $ownDevice = $this->createDevice();
        $otherDevice = $this->createDevice();
        $otherSession = $this->createSession($otherDevice);

        Sanctum::actingAs($ownDevice);

        $this->postJson("/api/device/sessions/{$otherSession->id}/photos", [
            'photo' => UploadedFile::fake()->image('capture.jpg'),
            'capture_index' => 1,
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This session does not belong to this device.');
    }

    public function test_device_cannot_upload_the_same_capture_index_twice(): void
    {
        $device = $this->createDevice();
        $session = $this->createSession($device);
        $session->update(['payment_status' => 'paid', 'paid_at' => now()]);

        Sanctum::actingAs($device);

        $payload = [
            'photo' => UploadedFile::fake()->image('capture.jpg', 1200, 1800),
            'capture_index' => 1,
        ];

        $this->postJson("/api/device/sessions/{$session->id}/photos", $payload)
            ->assertCreated();

        $this->postJson("/api/device/sessions/{$session->id}/photos", [
            'photo' => UploadedFile::fake()->image('capture-duplicate.jpg', 1200, 1800),
            'capture_index' => 1,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This capture index has already been uploaded for the session.');

        $this->assertDatabaseCount('session_photos', 1);
    }

    public function test_device_cannot_upload_photo_before_payment_without_skip_voucher(): void
    {
        $device = $this->createDevice();
        $session = $this->createSession($device);

        Sanctum::actingAs($device);

        $this->postJson("/api/device/sessions/{$session->id}/photos", [
            'photo' => UploadedFile::fake()->image('capture.jpg'),
            'capture_index' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Payment is required before uploading photos.');
    }

    public function test_payment_check_marks_payment_as_required_without_skip_voucher(): void
    {
        $device = $this->createDevice();
        $session = $this->createSession($device);

        Sanctum::actingAs($device);

        $this->getJson("/api/device/sessions/{$session->id}/payment-check")
            ->assertOk()
            ->assertJsonPath('session_id', $session->id)
            ->assertJsonPath('payment_required', true)
            ->assertJsonPath('skip_reason', null)
            ->assertJsonPath('voucher', null);
    }

    public function test_payment_check_marks_payment_as_not_required_with_skip_voucher(): void
    {
        $device = $this->createDevice();
        $session = $this->createSession($device);

        SessionVoucher::create([
            'id' => (string) Str::uuid(),
            'session_id' => $session->id,
            'voucher_code' => 'SKIP-DEVICE-001',
            'voucher_type' => 'skip',
            'status' => 'applied',
            'applied_at' => now(),
        ]);

        Sanctum::actingAs($device);

        $this->getJson("/api/device/sessions/{$session->id}/payment-check")
            ->assertOk()
            ->assertJsonPath('payment_required', false)
            ->assertJsonPath('skip_reason', 'voucher_skip')
            ->assertJsonPath('voucher.voucher_code', 'SKIP-DEVICE-001')
            ->assertJsonPath('voucher.voucher_type', 'skip');
    }

    public function test_device_can_confirm_payment_then_upload_photo(): void
    {
        $device = $this->createDevice();
        $session = $this->createSession($device);

        Sanctum::actingAs($device);

        $this->postJson("/api/device/sessions/{$session->id}/confirm-payment", [
            'payment_ref' => 'PAY-REF-001',
            'payment_method' => 'qris',
            'amount' => 10000,
            'currency' => 'IDR',
        ])
            ->assertOk()
            ->assertJsonPath('payment_status', 'paid')
            ->assertJsonPath('payment_ref', 'PAY-REF-001')
            ->assertJsonPath('payment_method', 'qris');

        $this->postJson("/api/device/sessions/{$session->id}/photos", [
            'photo' => UploadedFile::fake()->image('capture.jpg', 1200, 1800),
            'capture_index' => 1,
        ])
            ->assertCreated();
    }

    public function test_manual_payment_session_requires_editor_approval_to_unlock(): void
    {
        $device = $this->createDevice();
        $editor = $this->createEditorUser();

        Sanctum::actingAs($device);

        $createResponse = $this->postJson('/api/device/sessions', [
            'payment_method' => 'manual',
            'customer_whatsapp' => '6281234567890',
            'additional_print_count' => 2,
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('payment_status', 'pending')
            ->assertJsonPath('payment_required', true)
            ->assertJsonPath('unlock_photo', false)
            ->assertJsonPath('manual_payment_requested', true)
            ->assertJsonPath('manual_payment_status', 'pending_approval')
            ->assertJsonPath('customer_tier', 'regular')
            ->assertJsonPath('customer_whatsapp', '6281234567890')
            ->assertJsonPath('additional_print_count', 2);

        $sessionId = (string) $createResponse->json('session_id');

        $this->postJson("/api/device/sessions/{$sessionId}/confirm-payment", [
            'payment_ref' => 'DEVICE-SHOULD-BLOCK',
            'payment_method' => 'manual',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Manual payment session must be approved by editor.');

        $this->getJson("/api/device/sessions/{$sessionId}/payment-check")
            ->assertOk()
            ->assertJsonPath('payment_required', true)
            ->assertJsonPath('manual_payment_status', 'pending_approval');

        Sanctum::actingAs($editor);

        $this->postJson("/api/editor/sessions/{$sessionId}/manual-payment/approve", [
            'payment_ref' => 'MANUAL-APPROVED-001',
            'notes' => 'Cash confirmed by operator.',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Manual payment approved.')
            ->assertJsonPath('payment_status', 'paid')
            ->assertJsonPath('manual_payment_status', 'approved')
            ->assertJsonPath('unlock_photo', true)
            ->assertJsonPath('payment_ref', 'MANUAL-APPROVED-001');

        Sanctum::actingAs($device);

        $this->getJson("/api/device/sessions/{$sessionId}/payment-check")
            ->assertOk()
            ->assertJsonPath('payment_required', false)
            ->assertJsonPath('payment_status', 'paid')
            ->assertJsonPath('manual_payment_status', 'approved');

        $this->assertDatabaseHas('session_events', [
            'session_id' => $sessionId,
            'event_type' => 'manual_payment_requested',
        ]);

        $this->assertDatabaseHas('session_events', [
            'session_id' => $sessionId,
            'event_type' => 'manual_payment_approved',
        ]);

        $this->assertDatabaseHas('customers', [
            'customer_whatsapp' => '6281234567890',
            'tier' => 'regular',
        ]);
    }

    public function test_device_session_reuses_existing_customer_identity_for_same_whatsapp(): void
    {
        $device = $this->createDevice();
        Sanctum::actingAs($device);

        $firstSession = $this->postJson('/api/device/sessions', [
            'payment_method' => 'manual',
            'customer_whatsapp' => '081234567890',
        ])->assertCreated();

        $firstCustomerId = (string) $firstSession->json('customer_id');

        $secondSession = $this->postJson('/api/device/sessions', [
            'payment_method' => 'manual',
            'customer_whatsapp' => '6281234567890',
        ])->assertCreated();

        $secondCustomerId = (string) $secondSession->json('customer_id');

        $this->assertSame($firstCustomerId, $secondCustomerId);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customers', [
            'id' => $firstCustomerId,
            'customer_whatsapp' => '6281234567890',
        ]);
    }

    public function test_device_can_verify_valid_voucher_before_payment(): void
    {
        $device = $this->createDevice();
        $this->createMasterVoucher('VCHR-BEFOREPAY-01', 'skip');

        Sanctum::actingAs($device);

        $this->postJson('/api/device/vouchers/verify', [
            'voucher_code' => 'VCHR-BEFOREPAY-01',
        ])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('unlock_photo', true)
            ->assertJsonPath('voucher_code', 'VCHR-BEFOREPAY-01')
            ->assertJsonPath('voucher_type', 'skip')
            ->assertJsonPath('voucher.code', 'VCHR-BEFOREPAY-01');
    }

    public function test_device_can_get_payment_quote_with_percent_discount(): void
    {
        $device = $this->createDevice();
        $this->createMasterVoucher(
            code: 'PROMO-20',
            type: 'promo',
            discountType: 'percent',
            discountValue: 20
        );

        Sanctum::actingAs($device);

        $this->postJson('/api/device/payment-quote', [
            'subtotal_amount' => 100000,
            'voucher_code' => 'PROMO-20',
        ])
            ->assertOk()
            ->assertJsonPath('quote.subtotal_amount', fn ($value) => (float) $value === 100000.0)
            ->assertJsonPath('quote.discount_amount', fn ($value) => (float) $value === 20000.0)
            ->assertJsonPath('quote.total_due', fn ($value) => (float) $value === 80000.0)
            ->assertJsonPath('quote.payment_required', true)
            ->assertJsonPath('quote.unlock_photo', false);
    }

    public function test_device_verify_rejects_expired_voucher(): void
    {
        $device = $this->createDevice();
        $this->createMasterVoucher(
            code: 'PROMO-EXPIRED',
            type: 'promo',
            discountType: 'fixed',
            discountValue: 10000,
            validFrom: now()->subDays(10),
            validUntil: now()->subDay(),
        );

        Sanctum::actingAs($device);

        $this->postJson('/api/device/vouchers/verify', [
            'voucher_code' => 'PROMO-EXPIRED',
        ])
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('unlock_photo', false);
    }

    public function test_device_verify_rejects_not_started_voucher(): void
    {
        $device = $this->createDevice();
        $this->createMasterVoucher(
            code: 'PROMO-NOT-STARTED',
            type: 'promo',
            discountType: 'fixed',
            discountValue: 10000,
            validFrom: now()->addDay(),
            validUntil: now()->addDays(10),
        );

        Sanctum::actingAs($device);

        $this->postJson('/api/device/vouchers/verify', [
            'voucher_code' => 'PROMO-NOT-STARTED',
        ])
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('unlock_photo', false);
    }

    public function test_device_verify_rejects_usage_full_voucher(): void
    {
        $device = $this->createDevice();
        $this->createMasterVoucher(
            code: 'PROMO-USAGE-FULL',
            type: 'promo',
            discountType: 'fixed',
            discountValue: 10000,
            maxUsage: 5,
            usedCount: 5,
        );

        Sanctum::actingAs($device);

        $this->postJson('/api/device/vouchers/verify', [
            'voucher_code' => 'PROMO-USAGE-FULL',
        ])
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('unlock_photo', false);
    }

    public function test_device_quote_returns_min_purchase_not_met_reason(): void
    {
        $device = $this->createDevice();
        $this->createMasterVoucher(
            code: 'PROMO-MIN-PURCHASE',
            type: 'promo',
            discountType: 'fixed',
            discountValue: 20000,
            minPurchaseAmount: 100000,
        );

        Sanctum::actingAs($device);

        $this->postJson('/api/device/payment-quote', [
            'subtotal_amount' => 50000,
            'voucher_code' => 'PROMO-MIN-PURCHASE',
        ])
            ->assertOk()
            ->assertJsonPath('quote.discount_amount', fn ($value) => (float) $value === 0.0)
            ->assertJsonPath('quote.total_due', fn ($value) => (float) $value === 50000.0)
            ->assertJsonPath('quote.payment_required', true)
            ->assertJsonPath('quote.discount_reason', 'min_purchase_not_met');
    }

    public function test_device_quote_unlocks_photo_for_free_voucher_type(): void
    {
        $device = $this->createDevice();
        $this->createMasterVoucher(
            code: 'FREE-UNLOCK',
            type: 'free',
            discountType: null,
            discountValue: null,
        );

        Sanctum::actingAs($device);

        $this->postJson('/api/device/payment-quote', [
            'subtotal_amount' => 75000,
            'voucher_code' => 'FREE-UNLOCK',
        ])
            ->assertOk()
            ->assertJsonPath('quote.discount_amount', fn ($value) => (float) $value === 75000.0)
            ->assertJsonPath('quote.total_due', fn ($value) => (float) $value === 0.0)
            ->assertJsonPath('quote.payment_required', false)
            ->assertJsonPath('quote.unlock_photo', true);
    }

    public function test_device_create_session_with_promo_voucher_stays_pending_payment(): void
    {
        $device = $this->createDevice();
        $this->createMasterVoucher(
            code: 'PROMO-10',
            type: 'promo',
            discountType: 'percent',
            discountValue: 10
        );

        Sanctum::actingAs($device);

        $response = $this->postJson('/api/device/sessions', [
            'voucher_code' => 'PROMO-10',
        ]);

        $response->assertCreated()
            ->assertJsonPath('voucher_applied', true)
            ->assertJsonPath('voucher_type', 'promo')
            ->assertJsonPath('customer_tier', 'free')
            ->assertJsonPath('payment_status', 'pending');
    }

    public function test_device_can_create_paid_session_with_valid_voucher_and_upload_photo(): void
    {
        $device = $this->createDevice();
        $this->createMasterVoucher('VCHR-BEFOREPAY-02', 'skip');

        Sanctum::actingAs($device);

        $createResponse = $this->postJson('/api/device/sessions', [
            'voucher_code' => 'VCHR-BEFOREPAY-02',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('payment_status', 'paid')
            ->assertJsonPath('payment_required', false)
            ->assertJsonPath('unlock_photo', true)
            ->assertJsonPath('voucher_applied', true);

        $sessionId = (string) $createResponse->json('session_id');

        $this->postJson("/api/device/sessions/{$sessionId}/photos", [
            'photo' => UploadedFile::fake()->image('capture.jpg', 1200, 1800),
            'capture_index' => 1,
        ])
            ->assertCreated();
    }

    public function test_device_promo_before_payment_flow_is_consistent_end_to_end(): void
    {
        $device = $this->createDevice();
        $this->createMasterVoucher(
            code: 'PROMO-E2E-20K',
            type: 'promo',
            discountType: 'fixed',
            discountValue: 20000
        );

        Sanctum::actingAs($device);

        $this->postJson('/api/device/vouchers/verify', [
            'voucher_code' => 'PROMO-E2E-20K',
            'subtotal_amount' => 100000,
        ])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('unlock_photo', false)
            ->assertJsonPath('payment_required', true)
            ->assertJsonPath('quote.discount_amount', fn ($value) => (float) $value === 20000.0)
            ->assertJsonPath('quote.total_due', fn ($value) => (float) $value === 80000.0);

        $this->postJson('/api/device/payment-quote', [
            'subtotal_amount' => 100000,
            'voucher_code' => 'PROMO-E2E-20K',
        ])
            ->assertOk()
            ->assertJsonPath('quote.discount_amount', fn ($value) => (float) $value === 20000.0)
            ->assertJsonPath('quote.total_due', fn ($value) => (float) $value === 80000.0)
            ->assertJsonPath('quote.payment_required', true);

        $createResponse = $this->postJson('/api/device/sessions', [
            'voucher_code' => 'PROMO-E2E-20K',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('voucher_applied', true)
            ->assertJsonPath('voucher_type', 'promo')
            ->assertJsonPath('payment_status', 'pending');

        $sessionId = (string) $createResponse->json('session_id');

        $this->getJson("/api/device/sessions/{$sessionId}/payment-check")
            ->assertOk()
            ->assertJsonPath('payment_required', true)
            ->assertJsonPath('payment_status', 'pending')
            ->assertJsonPath('skip_reason', null)
            ->assertJsonPath('voucher.voucher_code', 'PROMO-E2E-20K');

        $this->postJson("/api/device/sessions/{$sessionId}/photos", [
            'photo' => UploadedFile::fake()->image('capture-before-paid.jpg', 1200, 1800),
            'capture_index' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Payment is required before uploading photos.');

        $this->assertDatabaseHas('session_events', [
            'session_id' => $sessionId,
            'event_type' => 'payment_gate_blocked',
        ]);

        $this->postJson("/api/device/sessions/{$sessionId}/confirm-payment", [
            'payment_ref' => 'PAY-E2E-001',
            'payment_method' => 'qris',
            'amount' => 80000,
            'currency' => 'IDR',
        ])
            ->assertOk()
            ->assertJsonPath('payment_status', 'paid')
            ->assertJsonPath('payment_required', false)
            ->assertJsonPath('unlock_photo', true);

        $this->assertDatabaseHas('session_events', [
            'session_id' => $sessionId,
            'event_type' => 'payment_confirmed',
        ]);

        $this->getJson("/api/device/sessions/{$sessionId}/payment-check")
            ->assertOk()
            ->assertJsonPath('payment_required', false)
            ->assertJsonPath('payment_status', 'paid');

        $this->postJson("/api/device/sessions/{$sessionId}/photos", [
            'photo' => UploadedFile::fake()->image('capture-after-paid.jpg', 1200, 1800),
            'capture_index' => 1,
        ])
            ->assertCreated();

        $this->assertDatabaseHas('session_events', [
            'session_id' => $sessionId,
            'event_type' => 'voucher_applied',
        ]);
    }

    public function test_device_cannot_complete_session_before_payment(): void
    {
        $device = $this->createDevice();
        $session = $this->createSession($device);
        $session->update(['captured_count' => 1]);

        Sanctum::actingAs($device);

        $this->postJson("/api/device/sessions/{$session->id}/complete", [
            'total_expected_photos' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Payment is required before completing this session.');
    }

    public function test_device_before_payment_contract_keys_are_stable(): void
    {
        $device = $this->createDevice();
        $this->createMasterVoucher(
            code: 'CONTRACT-PROMO-10',
            type: 'promo',
            discountType: 'percent',
            discountValue: 10
        );

        Sanctum::actingAs($device);

        $verify = $this->postJson('/api/device/vouchers/verify', [
            'voucher_code' => 'CONTRACT-PROMO-10',
            'subtotal_amount' => 100000,
        ]);
        $verify->assertOk()
            ->assertJsonPath('contract_version', '2026-04-17')
            ->assertJsonStructure([
                'contract_version',
                'valid',
                'unlock_photo',
                'payment_required',
                'message',
                'voucher_code',
                'voucher_type',
                'voucher' => ['code', 'type', 'voucher_code', 'voucher_type'],
                'quote' => ['subtotal_amount', 'discount_amount', 'total_due', 'payment_required', 'unlock_photo', 'discount_reason'],
            ]);

        $quote = $this->postJson('/api/device/payment-quote', [
            'subtotal_amount' => 100000,
            'voucher_code' => 'CONTRACT-PROMO-10',
        ]);
        $quote->assertOk()
            ->assertJsonPath('contract_version', '2026-04-17')
            ->assertJsonStructure([
                'contract_version',
                'message',
                'voucher_code',
                'voucher_type',
                'voucher' => ['code', 'type', 'voucher_code', 'voucher_type'],
                'quote' => ['subtotal_amount', 'discount_amount', 'total_due', 'payment_required', 'unlock_photo', 'discount_reason'],
            ]);

        $create = $this->postJson('/api/device/sessions', [
            'voucher_code' => 'CONTRACT-PROMO-10',
        ]);
        $create->assertCreated()
            ->assertJsonPath('contract_version', '2026-04-17')
            ->assertJsonStructure([
                'contract_version',
                'message',
                'session_id',
                'session_code',
                'station_id',
                'device_id',
                'status',
                'payment_status',
                'payment_required',
                'unlock_photo',
                'voucher_applied',
                'voucher_code',
                'voucher_type',
                'voucher',
            ]);

        $sessionId = (string) $create->json('session_id');

        $check = $this->getJson("/api/device/sessions/{$sessionId}/payment-check");
        $check->assertOk()
            ->assertJsonPath('contract_version', '2026-04-17')
            ->assertJsonStructure([
                'contract_version',
                'session_id',
                'session_code',
                'payment_status',
                'payment_required',
                'payment_unlocked',
                'skip_reason',
                'voucher_code',
                'voucher_type',
                'voucher' => ['id', 'voucher_code', 'voucher_type', 'status'],
            ]);

        $confirm = $this->postJson("/api/device/sessions/{$sessionId}/confirm-payment", [
            'payment_ref' => 'CONTRACT-PAY-001',
            'payment_method' => 'qris',
            'amount' => 90000,
            'currency' => 'IDR',
        ]);
        $confirm->assertOk()
            ->assertJsonPath('contract_version', '2026-04-17')
            ->assertJsonStructure([
                'contract_version',
                'message',
                'session_id',
                'session_code',
                'payment_status',
                'payment_required',
                'unlock_photo',
                'payment_ref',
                'payment_method',
                'paid_at',
            ]);
    }

    public function test_device_can_sync_master_data_with_pricing_and_templates(): void
    {
        $device = $this->createDevice();

        $device->station()->update([
            'photobooth_price' => 45000,
            'additional_print_price' => 7000,
            'currency_code' => 'IDR',
        ]);

        $template = Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-SYNC-001',
            'template_name' => 'Sync Template',
            'category' => 'photobooth',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'config_json' => ['background_color' => '#ffffff'],
            'status' => 'active',
        ]);

        TemplateSlot::create([
            'id' => (string) Str::uuid(),
            'template_id' => $template->id,
            'slot_index' => 1,
            'x' => 0,
            'y' => 0,
            'width' => 1200,
            'height' => 900,
            'rotation' => 0,
            'border_radius' => 0,
        ]);

        Sanctum::actingAs($device);

        $this->getJson('/api/device/master-data')
            ->assertOk()
            ->assertJsonPath('contract_version', '2026-04-17')
            ->assertJsonPath('pricing.photobooth_price', fn ($value) => (float) $value === 45000.0)
            ->assertJsonPath('pricing.additional_print_price', fn ($value) => (float) $value === 7000.0)
            ->assertJsonPath('pricing.currency_code', 'IDR')
            ->assertJsonPath('templates.0.template_code', 'TPL-SYNC-001')
            ->assertJsonPath('templates.0.slots.0.slot_index', 1)
            ->assertJsonStructure([
                'contract_version',
                'station' => [
                    'id',
                    'station_code',
                    'station_name',
                    'location_name',
                    'timezone',
                    'local_ip',
                    'status',
                ],
                'pricing' => [
                    'photobooth_price',
                    'additional_print_price',
                    'currency_code',
                ],
                'templates' => [[
                    'id',
                    'template_code',
                    'template_name',
                    'category',
                    'paper_size',
                    'canvas_width',
                    'canvas_height',
                    'thumbnail_url',
                    'preview_url',
                    'overlay_url',
                    'config',
                    'slots',
                ]],
            ]);
    }

    public function test_device_can_send_heartbeat_to_update_operational_metadata(): void
    {
        $device = $this->createDevice();

        Sanctum::actingAs($device);

        $this
            ->withServerVariables(['REMOTE_ADDR' => '192.168.88.248'])
            ->postJson('/api/device/heartbeat', [
                'device_type' => 'minipc_kiosk',
                'local_ip' => '192.168.88.248',
                'battery_percent' => 87,
                'network_strength' => 92,
                'app_version' => '1.2.3',
                'os_name' => 'Windows',
                'os_version' => '11',
                'capabilities' => [
                    'camera' => true,
                    'printer' => true,
                    'offline_queue' => true,
                    'local_render' => false,
                ],
                'metrics' => [
                    'disk_free_mb' => 51200,
                ],
                'last_sync_at' => now()->subMinute()->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('server_time', fn ($value) => is_string($value) && $value !== '')
            ->assertJsonPath('message', 'Heartbeat received')
            ->assertJsonPath('device.device_type', 'minipc_kiosk')
            ->assertJsonPath('device.local_ip', '192.168.88.248')
            ->assertJsonPath('device.app_version', '1.2.3')
            ->assertJsonPath('device.os_name', 'Windows')
            ->assertJsonPath('device.os_version', '11')
            ->assertJsonPath('device.capabilities.camera', true)
            ->assertJsonPath('device.capabilities.printer', true);

        $this->assertDatabaseHas('android_devices', [
            'id' => $device->id,
            'device_type' => 'minipc_kiosk',
            'local_ip' => '192.168.88.248',
            'battery_percent' => 87,
            'app_version' => '1.2.3',
            'os_name' => 'Windows',
            'os_version' => '11',
        ]);

        $this->assertDatabaseHas('device_heartbeats', [
            'device_id' => $device->id,
            'device_type' => 'minipc_kiosk',
            'local_ip' => '192.168.88.248',
            'battery_percent' => 87,
            'network_strength' => 92,
            'app_version' => '1.2.3',
            'os_name' => 'Windows',
            'os_version' => '11',
        ]);
    }

    public function test_device_heartbeat_accepts_android_camel_case_status_payload(): void
    {
        $device = $this->createDevice();

        Sanctum::actingAs($device);

        $this
            ->withServerVariables(['REMOTE_ADDR' => '192.168.88.246'])
            ->postJson('/api/device/heartbeat', [
                'localIp' => '192.168.88.101',
                'appVersion' => '1.0.0-dev',
                'os' => 'Android 14',
                'capabilities' => 'camera=true, printer=false, offline_queue=true, local_render=true',
                'lastHeartbeatAt' => '2026-04-30T10:42:31Z',
                'lastSyncAt' => '2026-04-30T10:42:31Z',
                'lastResult' => 'success',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('server_time', fn ($value) => is_string($value) && $value !== '')
            ->assertJsonPath('device.local_ip', '192.168.88.246')
            ->assertJsonPath('device.app_version', '1.0.0-dev')
            ->assertJsonPath('device.os_name', 'Android')
            ->assertJsonPath('device.os_version', '14')
            ->assertJsonPath('device.capabilities.camera', true)
            ->assertJsonPath('device.capabilities.printer', false)
            ->assertJsonPath('device.capabilities.offline_queue', true)
            ->assertJsonPath('device.capabilities.local_render', true)
            ->assertJsonPath('device.last_heartbeat_at', '2026-04-30T10:42:31+07:00')
            ->assertJsonPath('device.last_sync_at', '2026-04-30T10:42:31+07:00');

        $this->assertDatabaseHas('android_devices', [
            'id' => $device->id,
            'local_ip' => '192.168.88.246',
            'app_version' => '1.0.0-dev',
            'os_name' => 'Android',
            'os_version' => '14',
        ]);

        $this->assertDatabaseHas('device_heartbeats', [
            'device_id' => $device->id,
            'local_ip' => '192.168.88.246',
            'app_version' => '1.0.0-dev',
            'os_name' => 'Android',
            'os_version' => '14',
        ]);
    }

    public function test_device_can_fetch_templates_from_dedicated_endpoint(): void
    {
        $device = $this->createDevice();

        $activeTemplate = Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-DEVICE-001',
            'template_name' => 'Device Template Active',
            'category' => 'photobooth',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'config_json' => ['background_color' => '#ffffff'],
            'status' => 'active',
        ]);

        TemplateSlot::create([
            'id' => (string) Str::uuid(),
            'template_id' => $activeTemplate->id,
            'slot_index' => 1,
            'x' => 10,
            'y' => 20,
            'width' => 580,
            'height' => 860,
            'rotation' => 0,
            'border_radius' => 8,
        ]);

        Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-DEVICE-002',
            'template_name' => 'Device Template Inactive',
            'category' => 'photobooth',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'config_json' => ['background_color' => '#000000'],
            'status' => 'inactive',
        ]);

        Sanctum::actingAs($device);

        $this->getJson('/api/device/templates')
            ->assertOk()
            ->assertJsonPath('contract_version', '2026-04-17')
            ->assertJsonPath('filters.category', null)
            ->assertJsonPath('filters.paper_size', null)
            ->assertJsonPath('filters.q', null)
            ->assertJsonPath('filters.updated_since', null)
            ->assertJsonPath('filters.limit', 100)
            ->assertJsonPath('filters.include_slots', true)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('templates.0.template_code', 'TPL-DEVICE-001')
            ->assertJsonPath('templates.0.slots.0.slot_index', 1)
            ->assertJsonStructure([
                'contract_version',
                'filters' => [
                    'category',
                    'paper_size',
                    'q',
                    'updated_since',
                    'limit',
                    'include_slots',
                ],
                'count',
                'templates' => [[
                    'id',
                    'template_code',
                    'template_name',
                    'category',
                    'paper_size',
                    'canvas_width',
                    'canvas_height',
                    'thumbnail_url',
                    'preview_url',
                    'overlay_url',
                    'config',
                    'slots',
                ]],
            ]);
    }

    public function test_device_can_filter_templates_by_category_paper_size_and_search_query(): void
    {
        $device = $this->createDevice();

        Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-WED-4R',
            'template_name' => 'Wedding Portrait',
            'category' => 'wedding',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'status' => 'active',
        ]);

        Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-WED-6R',
            'template_name' => 'Wedding Landscape',
            'category' => 'wedding',
            'paper_size' => '6R',
            'canvas_width' => 1800,
            'canvas_height' => 1200,
            'status' => 'active',
        ]);

        Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-BDAY-4R',
            'template_name' => 'Birthday Party',
            'category' => 'birthday',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'status' => 'active',
        ]);

        Sanctum::actingAs($device);

        $this->getJson('/api/device/templates?category=wedding&paper_size=4R&q=portrait')
            ->assertOk()
            ->assertJsonPath('filters.category', 'wedding')
            ->assertJsonPath('filters.paper_size', '4R')
            ->assertJsonPath('filters.q', 'portrait')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('templates.0.template_code', 'TPL-WED-4R')
            ->assertJsonPath('templates.0.template_name', 'Wedding Portrait');
    }

    public function test_device_can_filter_templates_by_updated_since_limit_and_exclude_slots(): void
    {
        $device = $this->createDevice();

        $oldTemplate = Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-OLD-001',
            'template_name' => 'Old Template',
            'category' => 'photobooth',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'status' => 'active',
        ]);
        DB::table('templates')
            ->where('id', $oldTemplate->id)
            ->update([
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subDays(7),
            ]);
        TemplateSlot::create([
            'id' => (string) Str::uuid(),
            'template_id' => $oldTemplate->id,
            'slot_index' => 1,
            'x' => 0,
            'y' => 0,
            'width' => 1200,
            'height' => 900,
            'rotation' => 0,
            'border_radius' => 0,
        ]);

        $newTemplateA = Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-NEW-001',
            'template_name' => 'New Template A',
            'category' => 'photobooth',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'status' => 'active',
        ]);
        DB::table('templates')
            ->where('id', $newTemplateA->id)
            ->update([
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ]);
        TemplateSlot::create([
            'id' => (string) Str::uuid(),
            'template_id' => $newTemplateA->id,
            'slot_index' => 1,
            'x' => 10,
            'y' => 10,
            'width' => 500,
            'height' => 700,
            'rotation' => 0,
            'border_radius' => 0,
        ]);

        $newTemplateB = Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-NEW-002',
            'template_name' => 'New Template B',
            'category' => 'photobooth',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'status' => 'active',
        ]);
        DB::table('templates')
            ->where('id', $newTemplateB->id)
            ->update([
                'created_at' => now()->subHour(),
                'updated_at' => now()->subHour(),
            ]);
        TemplateSlot::create([
            'id' => (string) Str::uuid(),
            'template_id' => $newTemplateB->id,
            'slot_index' => 1,
            'x' => 20,
            'y' => 20,
            'width' => 600,
            'height' => 800,
            'rotation' => 0,
            'border_radius' => 0,
        ]);

        Sanctum::actingAs($device);

        $updatedSince = now()->subDay()->toDateTimeString();
        $encodedUpdatedSince = urlencode($updatedSince);

        $this->getJson("/api/device/templates?updated_since={$encodedUpdatedSince}&limit=1&include_slots=false")
            ->assertOk()
            ->assertJsonPath('filters.updated_since', $updatedSince)
            ->assertJsonPath('filters.limit', 1)
            ->assertJsonPath('filters.include_slots', false)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('templates.0.template_code', 'TPL-NEW-002')
            ->assertJsonPath('templates.0.slots', []);
    }

    public function test_device_can_fetch_single_template_detail_and_inactive_template_returns_404(): void
    {
        $device = $this->createDevice();

        $activeTemplate = Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-DETAIL-001',
            'template_name' => 'Device Template Detail',
            'category' => 'photobooth',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'config_json' => ['background_color' => '#ffffff'],
            'status' => 'active',
        ]);

        TemplateSlot::create([
            'id' => (string) Str::uuid(),
            'template_id' => $activeTemplate->id,
            'slot_index' => 1,
            'x' => 0,
            'y' => 0,
            'width' => 1200,
            'height' => 900,
            'rotation' => 0,
            'border_radius' => 0,
        ]);

        $inactiveTemplate = Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-DETAIL-002',
            'template_name' => 'Device Template Hidden',
            'category' => 'photobooth',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'config_json' => ['background_color' => '#000000'],
            'status' => 'inactive',
        ]);

        Sanctum::actingAs($device);

        $this->getJson("/api/device/templates/{$activeTemplate->id}")
            ->assertOk()
            ->assertJsonPath('contract_version', '2026-04-17')
            ->assertJsonPath('template.template_code', 'TPL-DETAIL-001')
            ->assertJsonPath('template.slots.0.slot_index', 1)
            ->assertJsonStructure([
                'contract_version',
                'template' => [
                    'id',
                    'template_code',
                    'template_name',
                    'category',
                    'paper_size',
                    'canvas_width',
                    'canvas_height',
                    'thumbnail_url',
                    'preview_url',
                    'overlay_url',
                    'config',
                    'slots',
                ],
            ]);

        $this->getJson("/api/device/templates/{$activeTemplate->id}?include_slots=false")
            ->assertOk()
            ->assertJsonPath('template.template_code', 'TPL-DETAIL-001')
            ->assertJsonPath('template.slots', []);

        $this->getJson("/api/device/templates/{$inactiveTemplate->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Template not found.');
    }

    public function test_device_template_preview_url_prefers_thumbnail_asset(): void
    {
        $device = $this->createDevice();

        $template = Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-THUMB-001',
            'template_name' => 'Template Thumbnail',
            'category' => 'photobooth',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'preview_url' => 'https://legacy.example/preview.png',
            'status' => 'active',
        ]);

        $thumbnailAsset = AssetFile::create([
            'id' => (string) Str::uuid(),
            'storage_disk' => 'public',
            'file_path' => 'templates/thumb-test/thumbnail.png',
            'file_name' => 'thumbnail.png',
            'file_ext' => 'png',
            'mime_type' => 'image/png',
            'file_size_bytes' => 1024,
            'file_category' => 'template_thumbnail',
            'created_by_type' => 'system',
        ]);

        TemplateAsset::create([
            'id' => (string) Str::uuid(),
            'template_id' => $template->id,
            'asset_type' => 'thumbnail_image',
            'file_id' => $thumbnailAsset->id,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($device);

        $response = $this->getJson("/api/device/templates/{$template->id}")
            ->assertOk();

        $thumbnailUrl = (string) $response->json('template.thumbnail_url');
        $previewUrl = (string) $response->json('template.preview_url');

        $this->assertStringContainsString('/api/device/template-assets/', $thumbnailUrl);
        $this->assertStringContainsString('signature=', $thumbnailUrl);
        $this->assertStringContainsString('expires=', $thumbnailUrl);
        $this->assertSame($thumbnailUrl, $previewUrl);
    }

    public function test_device_can_download_template_asset_via_signed_url(): void
    {
        $device = $this->createDevice();

        $template = Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-SIGNED-001',
            'template_name' => 'Template Signed Asset',
            'category' => 'photobooth',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'status' => 'active',
        ]);

        $thumbnailPath = 'templates/thumb-test/signed-thumbnail.png';
        $fakeThumbnail = UploadedFile::fake()->image('signed-thumbnail.png', 100, 100);
        Storage::disk('public')->put(
            $thumbnailPath,
            file_get_contents($fakeThumbnail->getRealPath()) ?: ''
        );

        $thumbnailAsset = AssetFile::create([
            'id' => (string) Str::uuid(),
            'storage_disk' => 'public',
            'file_path' => $thumbnailPath,
            'file_name' => 'signed-thumbnail.png',
            'file_ext' => 'png',
            'mime_type' => 'image/png',
            'file_size_bytes' => Storage::disk('public')->size($thumbnailPath),
            'file_category' => 'template_thumbnail',
            'created_by_type' => 'system',
        ]);

        TemplateAsset::create([
            'id' => (string) Str::uuid(),
            'template_id' => $template->id,
            'asset_type' => 'thumbnail_image',
            'file_id' => $thumbnailAsset->id,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($device);

        $response = $this->getJson("/api/device/templates/{$template->id}")
            ->assertOk();

        $signedUrl = (string) $response->json('template.thumbnail_url');
        $path = parse_url($signedUrl, PHP_URL_PATH);
        $query = parse_url($signedUrl, PHP_URL_QUERY);
        $signedPath = $path.($query ? '?'.$query : '');

        $this->get($signedPath)
            ->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertHeader('content-disposition', 'inline; filename="signed-thumbnail.png"')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('accept-ranges', 'bytes')
            ->assertHeader('x-asset-id', $thumbnailAsset->id)
            ->assertHeader('x-asset-size', (string) Storage::disk('public')->size($thumbnailPath));
    }

    public function test_device_can_diagnose_template_asset_with_auth_token(): void
    {
        $device = $this->createDevice();

        $thumbnailPath = 'templates/thumb-test/diagnose-thumbnail.png';
        $fakeThumbnail = UploadedFile::fake()->image('diagnose-thumbnail.png', 100, 100);
        Storage::disk('public')->put(
            $thumbnailPath,
            file_get_contents($fakeThumbnail->getRealPath()) ?: ''
        );

        $thumbnailAsset = AssetFile::create([
            'id' => (string) Str::uuid(),
            'storage_disk' => 'public',
            'file_path' => $thumbnailPath,
            'file_name' => 'diagnose-thumbnail.png',
            'file_ext' => 'png',
            'mime_type' => 'image/png',
            'file_size_bytes' => Storage::disk('public')->size($thumbnailPath),
            'file_category' => 'template_thumbnail',
            'created_by_type' => 'system',
        ]);

        Sanctum::actingAs($device);

        $this->getJson("/api/device/template-assets/{$thumbnailAsset->id}/diagnose")
            ->assertOk()
            ->assertJsonPath('asset_id', $thumbnailAsset->id)
            ->assertJsonPath('file_category', 'template_thumbnail')
            ->assertJsonPath('category_allowed', true)
            ->assertJsonPath('storage_disk', 'public')
            ->assertJsonPath('file_path', $thumbnailPath)
            ->assertJsonPath('disk_exists', true)
            ->assertJsonPath('absolute_file_exists', true)
            ->assertJsonPath('actual_file_size_bytes', Storage::disk('public')->size($thumbnailPath))
            ->assertJsonPath('png_signature_valid', true)
            ->assertJsonPath('diagnostic_error', null);
    }

    public function test_device_template_asset_supports_partial_content_range(): void
    {
        $device = $this->createDevice();

        $template = Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-SIGNED-002',
            'template_name' => 'Template Signed Range Asset',
            'category' => 'photobooth',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'status' => 'active',
        ]);

        $thumbnailPath = 'templates/thumb-test/signed-range-thumbnail.png';
        $fakeThumbnail = UploadedFile::fake()->image('signed-range-thumbnail.png', 120, 120);
        Storage::disk('public')->put(
            $thumbnailPath,
            file_get_contents($fakeThumbnail->getRealPath()) ?: ''
        );

        $thumbnailAsset = AssetFile::create([
            'id' => (string) Str::uuid(),
            'storage_disk' => 'public',
            'file_path' => $thumbnailPath,
            'file_name' => 'signed-range-thumbnail.png',
            'file_ext' => 'png',
            'mime_type' => 'image/png',
            'file_size_bytes' => Storage::disk('public')->size($thumbnailPath),
            'file_category' => 'template_thumbnail',
            'created_by_type' => 'system',
        ]);

        TemplateAsset::create([
            'id' => (string) Str::uuid(),
            'template_id' => $template->id,
            'asset_type' => 'thumbnail_image',
            'file_id' => $thumbnailAsset->id,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($device);

        $response = $this->getJson("/api/device/templates/{$template->id}")
            ->assertOk();

        $signedUrl = (string) $response->json('template.thumbnail_url');
        $path = parse_url($signedUrl, PHP_URL_PATH);
        $query = parse_url($signedUrl, PHP_URL_QUERY);
        $signedPath = $path.($query ? '?'.$query : '');

        $partialResponse = $this->withHeaders(['Range' => 'bytes=0-31'])
            ->get($signedPath)
            ->assertStatus(206)
            ->assertHeader('accept-ranges', 'bytes')
            ->assertHeader('content-length', '32');
        $this->assertStringStartsWith(
            'bytes 0-31/',
            (string) $partialResponse->headers->get('content-range')
        );

        $invalidPartialResponse = $this->withHeaders(['Range' => 'bytes=999999-1000000'])
            ->get($signedPath)
            ->assertStatus(416)
            ->assertHeader('accept-ranges', 'bytes');
        $this->assertStringStartsWith(
            'bytes */',
            (string) $invalidPartialResponse->headers->get('content-range')
        );
    }

    public function test_device_template_asset_can_offload_with_x_sendfile_header(): void
    {
        Config::set('filesystems.template_assets.delivery_driver', 'x_sendfile');
        Config::set('filesystems.template_assets.x_sendfile_header', 'X-Sendfile');

        $device = $this->createDevice();

        $template = Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-SIGNED-003',
            'template_name' => 'Template Signed X-Sendfile Asset',
            'category' => 'photobooth',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'status' => 'active',
        ]);

        $thumbnailPath = 'templates/thumb-test/signed-xsendfile-thumbnail.png';
        $fakeThumbnail = UploadedFile::fake()->image('signed-xsendfile-thumbnail.png', 120, 120);
        Storage::disk('public')->put(
            $thumbnailPath,
            file_get_contents($fakeThumbnail->getRealPath()) ?: ''
        );

        $thumbnailAsset = AssetFile::create([
            'id' => (string) Str::uuid(),
            'storage_disk' => 'public',
            'file_path' => $thumbnailPath,
            'file_name' => 'signed-xsendfile-thumbnail.png',
            'file_ext' => 'png',
            'mime_type' => 'image/png',
            'file_size_bytes' => Storage::disk('public')->size($thumbnailPath),
            'file_category' => 'template_thumbnail',
            'created_by_type' => 'system',
        ]);

        TemplateAsset::create([
            'id' => (string) Str::uuid(),
            'template_id' => $template->id,
            'asset_type' => 'thumbnail_image',
            'file_id' => $thumbnailAsset->id,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($device);

        $response = $this->getJson("/api/device/templates/{$template->id}")
            ->assertOk();

        $signedUrl = (string) $response->json('template.thumbnail_url');
        $path = parse_url($signedUrl, PHP_URL_PATH);
        $query = parse_url($signedUrl, PHP_URL_QUERY);
        $signedPath = $path.($query ? '?'.$query : '');

        $absolutePath = Storage::disk('public')->path($thumbnailPath);

        $this->get($signedPath)
            ->assertOk()
            ->assertHeader('x-sendfile', $absolutePath)
            ->assertHeader('content-type', 'image/png')
            ->assertHeader('accept-ranges', 'bytes');
    }

    public function test_device_can_create_edit_job_and_render_via_device_endpoints(): void
    {
        $this->createEditorUser();
        $device = $this->createDevice();
        $session = $this->createSession($device);
        $session->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $template = Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-DEVICE-RENDER-001',
            'template_name' => 'Device Render Template',
            'category' => 'photobooth',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'status' => 'active',
        ]);

        TemplateSlot::create([
            'id' => (string) Str::uuid(),
            'template_id' => $template->id,
            'slot_index' => 1,
            'x' => 0,
            'y' => 0,
            'width' => 1200,
            'height' => 1800,
            'rotation' => 0,
            'border_radius' => 0,
        ]);

        Sanctum::actingAs($device);

        $this->postJson("/api/device/sessions/{$session->id}/photos", [
            'photo' => UploadedFile::fake()->image('capture.jpg', 1200, 1800),
            'capture_index' => 1,
        ])->assertCreated();

        $this->postJson("/api/device/sessions/{$session->id}/complete", [
            'total_expected_photos' => 1,
        ])->assertOk();

        $sessionPhotoId = (string) $session->fresh()
            ->photos()
            ->orderBy('capture_index')
            ->value('id');

        $editJobResponse = $this->postJson("/api/device/sessions/{$session->id}/edit-jobs", [
            'template_id' => $template->id,
            'items' => [
                [
                    'session_photo_id' => $sessionPhotoId,
                    'slot_index' => 1,
                ],
            ],
        ]);

        $editJobResponse->assertCreated()
            ->assertJsonPath('session_id', $session->id)
            ->assertJsonPath('session_status', 'editing');

        $editJobId = (string) $editJobResponse->json('edit_job_id');

        $this->postJson("/api/device/edit-jobs/{$editJobId}/render")
            ->assertCreated()
            ->assertJsonPath('status', 'ready_print')
            ->assertJsonPath('rendered_output_id', fn ($value) => filled($value))
            ->assertJsonPath('file_url', fn ($value) => is_string($value) && $value !== '');
    }

    public function test_device_cannot_create_or_render_edit_jobs_for_other_device_sessions(): void
    {
        $editor = $this->createEditorUser();
        $ownerDevice = $this->createDevice();
        $otherDevice = $this->createDevice();
        $ownerSession = $this->createSession($ownerDevice);
        $ownerSession->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $template = Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-DEVICE-SEC-001',
            'template_name' => 'Device Security Template',
            'category' => 'photobooth',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'status' => 'active',
        ]);
        TemplateSlot::create([
            'id' => (string) Str::uuid(),
            'template_id' => $template->id,
            'slot_index' => 1,
            'x' => 0,
            'y' => 0,
            'width' => 1200,
            'height' => 1800,
            'rotation' => 0,
            'border_radius' => 0,
        ]);

        $asset = AssetFile::create([
            'id' => (string) Str::uuid(),
            'storage_disk' => 'public',
            'file_path' => 'stations/test/original.jpg',
            'file_name' => 'original.jpg',
            'file_ext' => 'jpg',
            'mime_type' => 'image/jpeg',
            'file_size_bytes' => 1024,
            'file_category' => 'original',
            'created_by_type' => 'device',
            'created_by_id' => $ownerDevice->id,
        ]);

        $sessionPhoto = SessionPhoto::create([
            'id' => (string) Str::uuid(),
            'session_id' => $ownerSession->id,
            'capture_index' => 1,
            'original_file_id' => $asset->id,
            'mime_type' => 'image/jpeg',
            'file_size_bytes' => 1024,
            'is_selected' => true,
            'uploaded_at' => now(),
        ]);

        Sanctum::actingAs($otherDevice);

        $this->postJson("/api/device/sessions/{$ownerSession->id}/edit-jobs", [
            'template_id' => $template->id,
            'items' => [
                [
                    'session_photo_id' => $sessionPhoto->id,
                    'slot_index' => 1,
                ],
            ],
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This session does not belong to this device.');

        $editJob = EditJob::create([
            'id' => (string) Str::uuid(),
            'session_id' => $ownerSession->id,
            'editor_id' => $editor->id,
            'template_id' => $template->id,
            'version_no' => 1,
            'status' => 'draft',
            'started_at' => now(),
        ]);

        $this->postJson("/api/device/edit-jobs/{$editJob->id}/render")
            ->assertForbidden()
            ->assertJsonPath('message', 'This edit job does not belong to this device session.');
    }

    public function test_device_can_upload_final_rendered_output_from_android_preview(): void
    {
        $this->createEditorUser();
        $device = $this->createDevice();
        $session = $this->createSession($device);
        $session->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $template = Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-DEVICE-FINAL-001',
            'template_name' => 'Device Final Render Template',
            'category' => 'photobooth',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'status' => 'active',
        ]);

        TemplateSlot::create([
            'id' => (string) Str::uuid(),
            'template_id' => $template->id,
            'slot_index' => 1,
            'x' => 0,
            'y' => 0,
            'width' => 1200,
            'height' => 1800,
            'rotation' => 0,
            'border_radius' => 0,
        ]);

        Sanctum::actingAs($device);

        $this->postJson("/api/device/sessions/{$session->id}/photos", [
            'photo' => UploadedFile::fake()->image('capture.jpg', 1200, 1800),
            'capture_index' => 1,
        ])->assertCreated();

        $this->postJson("/api/device/sessions/{$session->id}/complete", [
            'total_expected_photos' => 1,
        ])->assertOk();

        $sessionPhotoId = (string) $session->fresh()
            ->photos()
            ->orderBy('capture_index')
            ->value('id');

        $editJobResponse = $this->postJson("/api/device/sessions/{$session->id}/edit-jobs", [
            'template_id' => $template->id,
            'items' => [
                [
                    'session_photo_id' => $sessionPhotoId,
                    'slot_index' => 1,
                ],
            ],
        ]);

        $editJobResponse->assertCreated();

        $editJobId = (string) $editJobResponse->json('edit_job_id');

        $uploadResponse = $this->postJson("/api/device/sessions/{$session->id}/rendered-output", [
            'edit_job_id' => $editJobId,
            'rendered_image' => UploadedFile::fake()->image('android-final.png', 1200, 1800),
            'dpi' => 300,
        ]);

        $uploadResponse->assertCreated()
            ->assertJsonPath('message', 'Rendered output uploaded')
            ->assertJsonPath('status', 'ready_print')
            ->assertJsonPath('rendered_output_id', fn ($value) => filled($value))
            ->assertJsonPath('file_url', fn ($value) => is_string($value) && $value !== '');

        $renderedOutputId = (string) $uploadResponse->json('rendered_output_id');
        $filePath = (string) $uploadResponse->json('file_path');

        $this->assertDatabaseHas('rendered_outputs', [
            'id' => $renderedOutputId,
            'session_id' => $session->id,
            'edit_job_id' => $editJobId,
            'is_active' => true,
            'render_type' => 'final_print',
        ]);

        $this->assertDatabaseHas('photo_sessions', [
            'id' => $session->id,
            'status' => 'ready_print',
        ]);

        $this->assertDatabaseHas('asset_files', [
            'file_path' => $filePath,
            'file_category' => 'rendered',
            'created_by_type' => 'device',
            'created_by_id' => $device->id,
        ]);

        Storage::disk('public')->assertExists($filePath);
    }

    public function test_device_cannot_upload_final_rendered_output_for_other_device_session(): void
    {
        $editor = $this->createEditorUser();
        $ownerDevice = $this->createDevice();
        $otherDevice = $this->createDevice();
        $session = $this->createSession($ownerDevice);

        $template = Template::create([
            'id' => (string) Str::uuid(),
            'template_code' => 'TPL-DEVICE-FINAL-SEC-001',
            'template_name' => 'Device Final Security Template',
            'category' => 'photobooth',
            'paper_size' => '4R',
            'canvas_width' => 1200,
            'canvas_height' => 1800,
            'status' => 'active',
        ]);

        $editJob = EditJob::create([
            'id' => (string) Str::uuid(),
            'session_id' => $session->id,
            'editor_id' => $editor->id,
            'template_id' => $template->id,
            'version_no' => 1,
            'status' => 'draft',
            'started_at' => now(),
        ]);

        Sanctum::actingAs($otherDevice);

        $this->postJson("/api/device/sessions/{$session->id}/rendered-output", [
            'edit_job_id' => $editJob->id,
            'rendered_image' => UploadedFile::fake()->image('android-final.png', 1200, 1800),
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'This session does not belong to this device.');
    }

    protected function createDevice(): AndroidDevice
    {
        $station = Station::create([
            'id' => (string) Str::uuid(),
            'station_code' => 'ST-'.Str::upper(Str::random(6)),
            'station_name' => 'Main Station',
            'location_name' => 'Studio',
            'timezone' => 'Asia/Jakarta',
            'photobooth_price' => 35000,
            'additional_print_price' => 5000,
            'currency_code' => 'IDR',
            'status' => 'online',
        ]);

        return AndroidDevice::create([
            'id' => (string) Str::uuid(),
            'station_id' => $station->id,
            'device_code' => 'DV-'.Str::upper(Str::random(6)),
            'device_name' => 'Capture Device',
            'api_key_hash' => Hash::make('top-secret-device-key'),
            'status' => 'active',
        ]);
    }

    protected function createSession(AndroidDevice $device): PhotoSession
    {
        return PhotoSession::create([
            'id' => (string) Str::uuid(),
            'session_code' => 'SES-'.Str::upper(Str::random(8)),
            'station_id' => $device->station_id,
            'device_id' => $device->id,
            'session_type' => 'photobooth',
            'source_type' => 'android',
            'status' => 'created',
            'payment_status' => 'pending',
        ]);
    }

    protected function createMasterVoucher(
        string $code,
        string $type,
        ?string $discountType = null,
        ?float $discountValue = null,
        ?CarbonInterface $validFrom = null,
        ?CarbonInterface $validUntil = null,
        ?int $maxUsage = 100,
        ?int $usedCount = 0,
        ?float $minPurchaseAmount = null,
    ): void {
        DB::table('vouchers')->insert([
            'id' => (string) Str::uuid(),
            'voucher_code' => $code,
            'voucher_type' => $type,
            'status' => 'active',
            'valid_from' => $validFrom ?? now()->subHour(),
            'valid_until' => $validUntil ?? now()->addDay(),
            'max_usage' => $maxUsage,
            'used_count' => $usedCount,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'max_discount_amount' => null,
            'min_purchase_amount' => $minPurchaseAmount,
            'metadata_json' => json_encode(['seed' => 'test']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createEditorUser(): User
    {
        $user = User::factory()->create();
        $role = Role::create([
            'code' => 'admin',
            'name' => 'Administrator',
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }
}
