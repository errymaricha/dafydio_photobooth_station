<?php

namespace App\Observers;

use App\Models\PrintOrder;
use App\Services\Finance\AccrualPostingService;

class PrintOrderObserver
{
    public function __construct(
        private AccrualPostingService $accrualPostingService
    ) {}

    public function created(PrintOrder $printOrder): void
    {
        if ($printOrder->payment_status === 'paid') {
            $this->accrualPostingService->postPrintOrderPayment($printOrder);
        }
    }

    public function updated(PrintOrder $printOrder): void
    {
        if (! $printOrder->wasChanged('payment_status')) {
            return;
        }

        if ($printOrder->getOriginal('payment_status') === 'paid') {
            return;
        }

        if ($printOrder->payment_status !== 'paid') {
            return;
        }

        $this->accrualPostingService->postPrintOrderPayment($printOrder);
    }
}
