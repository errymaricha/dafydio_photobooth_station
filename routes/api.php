<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Device\AuthController;
use App\Http\Controllers\Api\Device\MasterDataController;
use App\Http\Controllers\Api\Device\SessionController as DeviceSessionController;
use App\Http\Controllers\Api\Device\UploadController;
use App\Http\Controllers\Api\Editor\CustomerCloudAccountController;
use App\Http\Controllers\Api\Editor\DashboardController;
use App\Http\Controllers\Api\Editor\EditJobController;
use App\Http\Controllers\Api\Editor\PricingController;
use App\Http\Controllers\Api\Editor\PrinterController;
use App\Http\Controllers\Api\Editor\PrintLogController;
use App\Http\Controllers\Api\Editor\PrintOrderController;
use App\Http\Controllers\Api\Editor\PrintQueueController;
use App\Http\Controllers\Api\Editor\RenderController;
use App\Http\Controllers\Api\Editor\SessionController as EditorSessionController;
use App\Http\Controllers\Api\Editor\SessionManualPaymentController;
use App\Http\Controllers\Api\Editor\SessionVoucherController;
use App\Http\Controllers\Api\Editor\TemplateController;
use App\Http\Controllers\Api\Editor\VoucherController;
use App\Http\Controllers\Api\PrintAgent\HeartbeatController;
use App\Http\Controllers\Api\PrintAgent\QueueController as PrintAgentQueueController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [LoginController::class, 'store']);

Route::prefix('device')->group(function () {
    Route::post('/auth', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/master-data', [MasterDataController::class, 'index']);
        Route::post('/vouchers/verify', [DeviceSessionController::class, 'verifyVoucher']);
        Route::post('/payment-quote', [DeviceSessionController::class, 'paymentQuote']);
        Route::post('/sessions', [DeviceSessionController::class, 'store']);
        Route::get('/sessions/{session}/payment-check', [DeviceSessionController::class, 'paymentCheck']);
        Route::post('/sessions/{session}/confirm-payment', [DeviceSessionController::class, 'confirmPayment']);
        Route::post('/sessions/{session}/photos', [UploadController::class, 'store']);
        Route::post('/sessions/{session}/complete', [DeviceSessionController::class, 'complete']);
    });
});

// 🔥 PISAH dari device
Route::prefix('editor')
    ->middleware(['auth:sanctum', 'role:admin,editor'])
    ->group(function () {
        Route::get('/sessions', [EditorSessionController::class, 'index']);
        Route::get('/sessions/{session}', [EditorSessionController::class, 'show']);
        Route::post('/sessions/{session}/manual-payment/approve', [SessionManualPaymentController::class, 'approve']);
        Route::post('/sessions/{session}/manual-payment/reject', [SessionManualPaymentController::class, 'reject']);
        Route::get('/sessions/{session}/photos/{photo}/face-fit', [EditorSessionController::class, 'faceFit']);
        Route::post('/sessions/{session}/edit-jobs', [EditJobController::class, 'store']);
        Route::post('/sessions/{session}/vouchers', [SessionVoucherController::class, 'store']);
        Route::post('/sessions/{session}/vouchers/{voucher}/revoke', [SessionVoucherController::class, 'revoke']);
        Route::post('/edit-jobs/{editJob}/render', [RenderController::class, 'store']);

        Route::post('/rendered-outputs/{renderedOutput}/print-orders', [PrintOrderController::class, 'store']);
        Route::get('/print-orders', [PrintOrderController::class, 'index']);
        Route::get('/print-orders/{printOrder}', [PrintOrderController::class, 'show']);

        Route::post('/print-orders/{printOrder}/queue', [PrintQueueController::class, 'store']);
        Route::post('/print-queue-jobs/{job}/retry', [PrintQueueController::class, 'retry']);
        Route::get('/print-queue-jobs', [PrintQueueController::class, 'indexJobs']);
        Route::get('/print-queue-jobs/{job}', [PrintQueueController::class, 'showJob']);
        Route::get('/print-queue-summary', [PrintQueueController::class, 'summary']);
        Route::get('/printers', [PrinterController::class, 'index']);
        Route::get('/printers/{printer}', [PrinterController::class, 'show']);

        Route::get('/print-logs', [PrintLogController::class, 'index']);
        Route::get('/print-orders/{printOrder}/logs', [PrintLogController::class, 'byOrder']);

        Route::post('/templates', [TemplateController::class, 'store']);
        Route::get('/templates', [TemplateController::class, 'index']);
        Route::get('/templates/{template}', [TemplateController::class, 'show']);
        Route::patch('/templates/{template}', [TemplateController::class, 'update']);
        Route::post('/templates/{template}/duplicate', [TemplateController::class, 'duplicate']);
        Route::post('/templates/{template}/overlay', [TemplateController::class, 'uploadOverlay']);
        Route::get('/templates/qr-preview', [TemplateController::class, 'qrPreview']);
        Route::delete('/templates/{template}', [TemplateController::class, 'destroy']);
        Route::post('/templates/{template}/slots', [TemplateController::class, 'updateSlots']);
        Route::post('/templates/{template}/slots/create', [TemplateController::class, 'storeSlot']);
        Route::delete('/templates/{template}/slots/{slotIndex}', [TemplateController::class, 'destroySlot']);

        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/customers', [CustomerCloudAccountController::class, 'index']);
        Route::post('/customers/cloud-account', [CustomerCloudAccountController::class, 'upsert']);
        Route::get('/customers/{customerWhatsapp}/history', [CustomerCloudAccountController::class, 'history']);
        Route::get('/customers/{customerWhatsapp}/cloud-sync', [CustomerCloudAccountController::class, 'cloudSync']);
        Route::get('/pricing', [PricingController::class, 'index']);
        Route::patch('/pricing', [PricingController::class, 'update']);
        Route::get('/voucher-library', [VoucherController::class, 'libraryIndex']);
        Route::post('/voucher-library', [VoucherController::class, 'libraryStore']);
        Route::post('/voucher-library/quote', [VoucherController::class, 'libraryQuote']);
        Route::patch('/voucher-library/{voucher}', [VoucherController::class, 'libraryUpdate']);
        Route::post('/voucher-library/{voucher}/deactivate', [VoucherController::class, 'libraryDeactivate']);
        Route::get('/vouchers', [VoucherController::class, 'index']);
        Route::post('/vouchers', [VoucherController::class, 'store']);
        Route::post('/vouchers/{voucher}/revoke', [VoucherController::class, 'revoke']);
    });

Route::prefix('print-agent')
    ->middleware(['auth:sanctum', 'role:print-agent,admin'])
    ->group(function () {
        Route::get('/jobs/next', [PrintAgentQueueController::class, 'next']);
        Route::post('/jobs/{job}/ack', [PrintAgentQueueController::class, 'ack']);
        Route::post('/jobs/{job}/complete', [PrintAgentQueueController::class, 'complete']);
        Route::post('/jobs/{job}/fail', [PrintAgentQueueController::class, 'fail']);
        Route::post('/heartbeat', [HeartbeatController::class, 'store']);

    });
