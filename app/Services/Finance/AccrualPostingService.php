<?php

namespace App\Services\Finance;

use App\Models\PhotoSession;
use App\Models\PrintOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccrualPostingService
{
    private const ACCOUNT_CASH_ON_HAND = '1100';

    private const ACCOUNT_CASH_IN_BANK = '1110';

    private const ACCOUNT_REVENUE_PHOTOBOOTH = '4100';

    private const ACCOUNT_REVENUE_ADDITIONAL_PRINT = '4110';

    private const ACCOUNT_REVENUE_PRINT_ORDER = '4120';

    public function postPhotoSessionPayment(PhotoSession $session): void
    {
        if ($session->payment_status !== 'paid') {
            return;
        }

        if ($session->payment_method === 'voucher') {
            return;
        }

        $alreadyPosted = DB::table('finance_journal_entries')
            ->where('source_type', 'photo_session_payment')
            ->where('source_id', $session->id)
            ->exists();

        if ($alreadyPosted) {
            return;
        }

        $station = $session->station()->first();

        if (! $station) {
            return;
        }

        $baseAmount = round((float) ($station->photobooth_price ?? 0), 2);
        $additionalAmount = round(
            ((float) ($station->additional_print_price ?? 0)) * (int) ($session->additional_print_count ?? 0),
            2
        );
        $totalAmount = round($baseAmount + $additionalAmount, 2);

        if ($totalAmount <= 0) {
            return;
        }

        $accountCodes = [
            $this->cashAccountCodeByPaymentMethod($session->payment_method),
            self::ACCOUNT_REVENUE_PHOTOBOOTH,
            self::ACCOUNT_REVENUE_ADDITIONAL_PRINT,
        ];
        $accountMap = $this->resolveAccountMap($accountCodes);

        if (! isset($accountMap[self::ACCOUNT_REVENUE_PHOTOBOOTH])) {
            return;
        }

        if (! isset($accountMap[self::ACCOUNT_REVENUE_ADDITIONAL_PRINT]) && $additionalAmount > 0) {
            return;
        }

        $cashAccountCode = $this->cashAccountCodeByPaymentMethod($session->payment_method);

        if (! isset($accountMap[$cashAccountCode])) {
            return;
        }

        DB::transaction(function () use (
            $accountMap,
            $additionalAmount,
            $baseAmount,
            $cashAccountCode,
            $session,
            $station,
            $totalAmount
        ): void {
            $journalEntryId = (string) Str::uuid();
            $entryDate = ($session->paid_at ?? now())->toDateString();
            $periodMonth = ($session->paid_at ?? now())->format('Y-m');

            DB::table('finance_journal_entries')->insert([
                'id' => $journalEntryId,
                'entry_no' => $this->generateEntryNumber('SPS'),
                'entry_date' => $entryDate,
                'period_month' => $periodMonth,
                'source_type' => 'photo_session_payment',
                'source_id' => $session->id,
                'source_ref' => $session->session_code,
                'station_id' => $session->station_id,
                'customer_id' => $session->customer_id,
                'currency_code' => $station->currency_code ?? 'IDR',
                'status' => 'posted',
                'memo' => 'Auto-posted photo session payment.',
                'posted_by' => null,
                'approved_by' => null,
                'posted_at' => $session->paid_at ?? now(),
                'reversed_entry_id' => null,
                'metadata_json' => json_encode([
                    'payment_method' => $session->payment_method,
                    'payment_ref' => $session->payment_ref,
                    'base_amount' => $baseAmount,
                    'additional_amount' => $additionalAmount,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $lineNo = 1;
            $this->insertJournalLine(
                journalEntryId: $journalEntryId,
                lineNo: $lineNo++,
                accountId: $accountMap[$cashAccountCode],
                stationId: $session->station_id,
                customerId: $session->customer_id,
                description: 'Receive cash/bank from session payment',
                debit: $totalAmount,
                credit: 0
            );

            if ($baseAmount > 0) {
                $this->insertJournalLine(
                    journalEntryId: $journalEntryId,
                    lineNo: $lineNo++,
                    accountId: $accountMap[self::ACCOUNT_REVENUE_PHOTOBOOTH],
                    stationId: $session->station_id,
                    customerId: $session->customer_id,
                    description: 'Recognize photobooth session revenue',
                    debit: 0,
                    credit: $baseAmount
                );
            }

            if ($additionalAmount > 0) {
                $this->insertJournalLine(
                    journalEntryId: $journalEntryId,
                    lineNo: $lineNo,
                    accountId: $accountMap[self::ACCOUNT_REVENUE_ADDITIONAL_PRINT],
                    stationId: $session->station_id,
                    customerId: $session->customer_id,
                    description: 'Recognize additional print revenue',
                    debit: 0,
                    credit: $additionalAmount
                );
            }
        });
    }

    public function postPrintOrderPayment(PrintOrder $printOrder): void
    {
        if ($printOrder->payment_status !== 'paid') {
            return;
        }

        $alreadyPosted = DB::table('finance_journal_entries')
            ->where('source_type', 'print_order_payment')
            ->where('source_id', $printOrder->id)
            ->exists();

        if ($alreadyPosted) {
            return;
        }

        $totalAmount = round((float) ($printOrder->total_amount ?? 0), 2);

        if ($totalAmount <= 0) {
            return;
        }

        $accountMap = $this->resolveAccountMap([
            self::ACCOUNT_CASH_ON_HAND,
            self::ACCOUNT_REVENUE_PRINT_ORDER,
        ]);

        if (! isset($accountMap[self::ACCOUNT_CASH_ON_HAND], $accountMap[self::ACCOUNT_REVENUE_PRINT_ORDER])) {
            return;
        }

        DB::transaction(function () use ($accountMap, $printOrder, $totalAmount): void {
            $entryDate = ($printOrder->ordered_at ?? now())->toDateString();
            $periodMonth = ($printOrder->ordered_at ?? now())->format('Y-m');
            $journalEntryId = (string) Str::uuid();

            DB::table('finance_journal_entries')->insert([
                'id' => $journalEntryId,
                'entry_no' => $this->generateEntryNumber('POP'),
                'entry_date' => $entryDate,
                'period_month' => $periodMonth,
                'source_type' => 'print_order_payment',
                'source_id' => $printOrder->id,
                'source_ref' => $printOrder->order_code,
                'station_id' => $printOrder->station_id,
                'customer_id' => null,
                'currency_code' => 'IDR',
                'status' => 'posted',
                'memo' => 'Auto-posted print order payment.',
                'posted_by' => null,
                'approved_by' => null,
                'posted_at' => $printOrder->ordered_at ?? now(),
                'reversed_entry_id' => null,
                'metadata_json' => json_encode([
                    'order_type' => $printOrder->order_type,
                    'source_type' => $printOrder->source_type,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->insertJournalLine(
                journalEntryId: $journalEntryId,
                lineNo: 1,
                accountId: $accountMap[self::ACCOUNT_CASH_ON_HAND],
                stationId: $printOrder->station_id,
                customerId: null,
                description: 'Receive cash from print order',
                debit: $totalAmount,
                credit: 0
            );

            $this->insertJournalLine(
                journalEntryId: $journalEntryId,
                lineNo: 2,
                accountId: $accountMap[self::ACCOUNT_REVENUE_PRINT_ORDER],
                stationId: $printOrder->station_id,
                customerId: null,
                description: 'Recognize print order revenue',
                debit: 0,
                credit: $totalAmount
            );
        });
    }

    /**
     * @param  array<int, string>  $accountCodes
     * @return array<string, string>
     */
    private function resolveAccountMap(array $accountCodes): array
    {
        return DB::table('finance_accounts')
            ->whereIn('account_code', array_values(array_unique($accountCodes)))
            ->pluck('id', 'account_code')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    private function cashAccountCodeByPaymentMethod(?string $paymentMethod): string
    {
        return $paymentMethod === 'qris'
            ? self::ACCOUNT_CASH_IN_BANK
            : self::ACCOUNT_CASH_ON_HAND;
    }

    private function generateEntryNumber(string $prefix): string
    {
        return sprintf(
            'JE-%s-%s-%s',
            $prefix,
            now()->format('Ymd'),
            strtoupper(Str::random(6))
        );
    }

    private function insertJournalLine(
        string $journalEntryId,
        int $lineNo,
        string $accountId,
        ?string $stationId,
        ?string $customerId,
        string $description,
        float $debit,
        float $credit
    ): void {
        DB::table('finance_journal_lines')->insert([
            'id' => (string) Str::uuid(),
            'journal_entry_id' => $journalEntryId,
            'line_no' => $lineNo,
            'account_id' => $accountId,
            'station_id' => $stationId,
            'customer_id' => $customerId,
            'description' => $description,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'currency_code' => 'IDR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
