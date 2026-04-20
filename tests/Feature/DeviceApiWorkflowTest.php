<?php

namespace Tests\Feature;

use App\Models\AndroidDevice;
use App\Models\PhotoSession;
use App\Models\Role;
use App\Models\SessionVoucher;
use App\Models\Station;
use App\Models\Template;
use App\Models\TemplateSlot;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
                    'preview_url',
                    'overlay_url',
                    'config',
                    'slots',
                ]],
            ]);
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
