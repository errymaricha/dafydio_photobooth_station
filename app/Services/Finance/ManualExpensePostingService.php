<?php

namespace App\Services\Finance;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManualExpensePostingService
{
    private const ACCOUNT_CASH_ON_HAND = '1100';

    private const ACCOUNT_CASH_IN_BANK = '1110';

    private const ACCOUNT_EXPENSE_OPERATIONAL = '5100';

    /**
     * @return array<string, string>
     */
    public function categoryOptions(): array
    {
        return [
            'consumables_paper_ink' => 'Consumables (Paper/Ink)',
            'printer_maintenance' => 'Maintenance Printer',
            'operator_salary' => 'Gaji Operator',
            'rent_utilities_internet' => 'Sewa/Listrik/Internet',
            'other' => 'Lainnya',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function paymentMethodOptions(): array
    {
        return [
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'qris' => 'QRIS',
            'ewallet' => 'E-Wallet',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{id: string, expense_no: string, entry_no: string}
     */
    public function createAndPost(array $payload, ?string $userId): array
    {
        $subtotalAmount = round((float) $payload['amount_subtotal'], 2);
        $taxAmount = round((float) ($payload['amount_tax'] ?? 0), 2);
        $totalAmount = round($subtotalAmount + $taxAmount, 2);
        $paymentMethod = (string) $payload['payment_method'];
        $cashAccountCode = $this->cashAccountCodeByPaymentMethod($paymentMethod);

        $accountMap = $this->resolveAccountMap([
            self::ACCOUNT_EXPENSE_OPERATIONAL,
            $cashAccountCode,
        ]);

        if (! isset($accountMap[self::ACCOUNT_EXPENSE_OPERATIONAL], $accountMap[$cashAccountCode])) {
            abort(422, 'Akun finance belum lengkap. Pastikan akun 1100/1110/5100 tersedia.');
        }

        $expenseId = (string) Str::uuid();
        $journalEntryId = (string) Str::uuid();
        $expenseNo = $this->generateExpenseNumber();
        $entryNo = $this->generateEntryNumber();
        $incurredAt = (string) $payload['incurred_at'];
        $currencyCode = strtoupper((string) ($payload['currency_code'] ?? 'IDR'));
        $paidAt = now();

        DB::transaction(function () use (
            $accountMap,
            $cashAccountCode,
            $currencyCode,
            $entryNo,
            $expenseId,
            $expenseNo,
            $incurredAt,
            $journalEntryId,
            $paidAt,
            $payload,
            $subtotalAmount,
            $taxAmount,
            $totalAmount,
            $userId
        ): void {
            DB::table('finance_expenses')->insert([
                'id' => $expenseId,
                'expense_no' => $expenseNo,
                'station_id' => $payload['station_id'] ?? null,
                'category_code' => $payload['category_code'],
                'category_name' => $this->categoryNameByCode((string) $payload['category_code']),
                'vendor_name' => $payload['vendor_name'] ?? null,
                'description' => $payload['description'] ?? null,
                'amount_subtotal' => $subtotalAmount,
                'amount_tax' => $taxAmount,
                'amount_total' => $totalAmount,
                'currency_code' => $currencyCode,
                'incurred_at' => $incurredAt,
                'due_at' => null,
                'paid_at' => $paidAt,
                'payment_method' => $payload['payment_method'],
                'payment_ref' => $payload['payment_ref'] ?? null,
                'status' => 'paid',
                'source_type' => 'manual_expense',
                'source_id' => null,
                'created_by' => $userId,
                'approved_by' => $userId,
                'paid_by' => $userId,
                'approved_at' => $paidAt,
                'attachment_path' => null,
                'metadata_json' => json_encode([
                    'input_mode' => 'manual',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('finance_journal_entries')->insert([
                'id' => $journalEntryId,
                'entry_no' => $entryNo,
                'entry_date' => $incurredAt,
                'period_month' => Carbon::parse($incurredAt)->format('Y-m'),
                'source_type' => 'manual_expense',
                'source_id' => $expenseId,
                'source_ref' => $expenseNo,
                'station_id' => $payload['station_id'] ?? null,
                'customer_id' => null,
                'currency_code' => $currencyCode,
                'status' => 'posted',
                'memo' => 'Manual expense posting.',
                'posted_by' => $userId,
                'approved_by' => $userId,
                'posted_at' => $paidAt,
                'reversed_entry_id' => null,
                'metadata_json' => json_encode([
                    'category_code' => $payload['category_code'],
                    'payment_method' => $payload['payment_method'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('finance_journal_lines')->insert([
                [
                    'id' => (string) Str::uuid(),
                    'journal_entry_id' => $journalEntryId,
                    'line_no' => 1,
                    'account_id' => $accountMap[self::ACCOUNT_EXPENSE_OPERATIONAL],
                    'station_id' => $payload['station_id'] ?? null,
                    'customer_id' => null,
                    'description' => 'Recognize manual operational expense',
                    'debit' => $totalAmount,
                    'credit' => 0,
                    'currency_code' => $currencyCode,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::uuid(),
                    'journal_entry_id' => $journalEntryId,
                    'line_no' => 2,
                    'account_id' => $accountMap[$cashAccountCode],
                    'station_id' => $payload['station_id'] ?? null,
                    'customer_id' => null,
                    'description' => 'Cash/Bank outflow for manual expense',
                    'debit' => 0,
                    'credit' => $totalAmount,
                    'currency_code' => $currencyCode,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        });

        return [
            'id' => $expenseId,
            'expense_no' => $expenseNo,
            'entry_no' => $entryNo,
        ];
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

    private function cashAccountCodeByPaymentMethod(string $paymentMethod): string
    {
        if ($paymentMethod === 'cash') {
            return self::ACCOUNT_CASH_ON_HAND;
        }

        return self::ACCOUNT_CASH_IN_BANK;
    }

    private function categoryNameByCode(string $categoryCode): string
    {
        return $this->categoryOptions()[$categoryCode] ?? $this->categoryOptions()['other'];
    }

    private function generateExpenseNumber(): string
    {
        return sprintf(
            'EXP-%s-%s',
            now()->format('Ymd'),
            strtoupper(Str::random(6))
        );
    }

    private function generateEntryNumber(): string
    {
        return sprintf(
            'JE-MEX-%s-%s',
            now()->format('Ymd'),
            strtoupper(Str::random(6))
        );
    }
}
