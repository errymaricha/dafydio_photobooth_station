<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveSessionManualPaymentRequest;
use App\Http\Requests\RejectSessionManualPaymentRequest;
use App\Models\PhotoSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionManualPaymentController extends Controller
{
    public function approve(
        ApproveSessionManualPaymentRequest $request,
        PhotoSession $session
    ): JsonResponse {
        if (! $this->isManualPaymentSession($session)) {
            return response()->json([
                'message' => 'Session is not a manual payment session.',
            ], 422);
        }

        if ($session->payment_status === 'paid') {
            return response()->json([
                'message' => 'Session payment is already approved.',
            ], 422);
        }

        $validated = $request->validated();
        $actor = $request->user();

        $paymentRef = $validated['payment_ref']
            ?? ('MANUAL-'.strtoupper(Str::random(10)));

        DB::transaction(function () use ($actor, $paymentRef, $session, $validated): void {
            $session->update([
                'payment_status' => 'paid',
                'payment_method' => 'manual',
                'payment_ref' => $paymentRef,
                'manual_payment_status' => 'approved',
                'manual_payment_reviewed_by' => $actor?->id,
                'manual_payment_reviewed_at' => now(),
                'manual_payment_notes' => $validated['notes'] ?? null,
                'paid_at' => now(),
            ]);

            $this->logManualPaymentEvent(
                sessionId: $session->id,
                eventType: 'manual_payment_approved',
                actorId: $actor?->id,
                payload: [
                    'payment_ref' => $paymentRef,
                    'notes' => $validated['notes'] ?? null,
                ],
            );
        });

        return response()->json([
            'message' => 'Manual payment approved.',
            'session_id' => $session->id,
            'session_code' => $session->session_code,
            'payment_status' => 'paid',
            'payment_required' => false,
            'unlock_photo' => true,
            'manual_payment_status' => 'approved',
            'payment_method' => 'manual',
            'payment_ref' => $paymentRef,
            'paid_at' => $session->fresh()->paid_at,
        ]);
    }

    public function reject(
        RejectSessionManualPaymentRequest $request,
        PhotoSession $session
    ): JsonResponse {
        if (! $this->isManualPaymentSession($session)) {
            return response()->json([
                'message' => 'Session is not a manual payment session.',
            ], 422);
        }

        if ($session->payment_status === 'paid') {
            return response()->json([
                'message' => 'Approved payment cannot be rejected.',
            ], 422);
        }

        $validated = $request->validated();
        $actor = $request->user();

        DB::transaction(function () use ($actor, $session, $validated): void {
            $session->update([
                'payment_status' => 'pending',
                'manual_payment_status' => 'rejected',
                'manual_payment_reviewed_by' => $actor?->id,
                'manual_payment_reviewed_at' => now(),
                'manual_payment_notes' => $validated['reason'],
            ]);

            $this->logManualPaymentEvent(
                sessionId: $session->id,
                eventType: 'manual_payment_rejected',
                actorId: $actor?->id,
                payload: [
                    'reason' => $validated['reason'],
                ],
            );
        });

        return response()->json([
            'message' => 'Manual payment rejected.',
            'session_id' => $session->id,
            'session_code' => $session->session_code,
            'payment_status' => 'pending',
            'payment_required' => true,
            'unlock_photo' => false,
            'manual_payment_status' => 'rejected',
            'reason' => $validated['reason'],
        ]);
    }

    private function isManualPaymentSession(PhotoSession $session): bool
    {
        return $session->payment_method === 'manual'
            || $session->manual_payment_status !== null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logManualPaymentEvent(
        string $sessionId,
        string $eventType,
        ?string $actorId,
        array $payload
    ): void {
        DB::table('session_events')->insert([
            'id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'event_type' => $eventType,
            'actor_type' => 'user',
            'actor_id' => $actorId,
            'payload_json' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
