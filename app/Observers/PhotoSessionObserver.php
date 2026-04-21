<?php

namespace App\Observers;

use App\Models\PhotoSession;
use App\Services\Finance\AccrualPostingService;

class PhotoSessionObserver
{
    public function __construct(
        private AccrualPostingService $accrualPostingService
    ) {}

    public function created(PhotoSession $photoSession): void
    {
        if ($photoSession->payment_status === 'paid') {
            $this->accrualPostingService->postPhotoSessionPayment($photoSession);
        }
    }

    public function updated(PhotoSession $photoSession): void
    {
        if (! $photoSession->wasChanged('payment_status')) {
            return;
        }

        if ($photoSession->getOriginal('payment_status') === 'paid') {
            return;
        }

        if ($photoSession->payment_status !== 'paid') {
            return;
        }

        $this->accrualPostingService->postPhotoSessionPayment($photoSession);
    }
}
