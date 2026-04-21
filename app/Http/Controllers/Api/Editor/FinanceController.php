<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Http\Requests\FinanceDailyPnlRequest;
use App\Http\Requests\FinanceExpenseIndexRequest;
use App\Http\Requests\FinanceExpenseStoreRequest;
use App\Http\Requests\FinanceTransactionIndexRequest;
use App\Services\Finance\ManualExpensePostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    public function __construct(
        private readonly ManualExpensePostingService $manualExpensePostingService
    ) {}

    public function accounts(): JsonResponse
    {
        $accounts = DB::table('finance_accounts')
            ->orderBy('account_code')
            ->get([
                'id',
                'account_code',
                'account_name',
                'account_type',
                'normal_balance',
                'parent_id',
                'level_no',
                'is_active',
            ])
            ->map(fn ($account): array => [
                'id' => (string) $account->id,
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'account_type' => $account->account_type,
                'normal_balance' => $account->normal_balance,
                'parent_id' => $account->parent_id ? (string) $account->parent_id : null,
                'level_no' => (int) $account->level_no,
                'is_active' => (bool) $account->is_active,
            ])
            ->values();

        return response()->json([
            'accounts' => $accounts,
        ]);
    }

    public function dailyPnl(FinanceDailyPnlRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $dateFrom = $validated['date_from'];
        $dateTo = $validated['date_to'];
        $stationId = $validated['station_id'] ?? null;

        $rows = DB::table('finance_journal_entries as entries')
            ->join('finance_journal_lines as lines', 'lines.journal_entry_id', '=', 'entries.id')
            ->join('finance_accounts as accounts', 'accounts.id', '=', 'lines.account_id')
            ->leftJoin('stations', 'stations.id', '=', 'entries.station_id')
            ->whereBetween('entries.entry_date', [$dateFrom, $dateTo])
            ->when(
                $stationId,
                fn ($query) => $query->where('entries.station_id', $stationId)
            )
            ->groupBy('entries.entry_date', 'entries.station_id', 'stations.station_code', 'stations.station_name')
            ->orderBy('entries.entry_date')
            ->orderBy('stations.station_code')
            ->get([
                'entries.entry_date',
                'entries.station_id',
                'stations.station_code',
                'stations.station_name',
                DB::raw("sum(case when accounts.account_type = 'revenue' then lines.credit - lines.debit else 0 end) as revenue_amount"),
                DB::raw("sum(case when accounts.account_type = 'expense' then lines.debit - lines.credit else 0 end) as expense_amount"),
            ]);

        $dailyRows = $rows->map(function ($row): array {
            $revenue = round((float) $row->revenue_amount, 2);
            $expense = round((float) $row->expense_amount, 2);
            $grossProfit = round($revenue - $expense, 2);

            return [
                'entry_date' => $row->entry_date,
                'station_id' => $row->station_id ? (string) $row->station_id : null,
                'station_code' => $row->station_code,
                'station_name' => $row->station_name,
                'revenue_amount' => $revenue,
                'expense_amount' => $expense,
                'gross_profit_amount' => $grossProfit,
                'net_profit_amount' => $grossProfit,
            ];
        })->values();

        $summary = [
            'revenue_amount' => round((float) $dailyRows->sum('revenue_amount'), 2),
            'expense_amount' => round((float) $dailyRows->sum('expense_amount'), 2),
            'gross_profit_amount' => round((float) $dailyRows->sum('gross_profit_amount'), 2),
            'net_profit_amount' => round((float) $dailyRows->sum('net_profit_amount'), 2),
            'row_count' => $dailyRows->count(),
        ];

        $byStation = $dailyRows
            ->groupBy('station_id')
            ->map(function ($items, $key): array {
                $first = $items->first();

                return [
                    'station_id' => $key,
                    'station_code' => $first['station_code'],
                    'station_name' => $first['station_name'],
                    'revenue_amount' => round((float) $items->sum('revenue_amount'), 2),
                    'expense_amount' => round((float) $items->sum('expense_amount'), 2),
                    'gross_profit_amount' => round((float) $items->sum('gross_profit_amount'), 2),
                    'net_profit_amount' => round((float) $items->sum('net_profit_amount'), 2),
                ];
            })
            ->values();

        return response()->json([
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'station_id' => $stationId,
            ],
            'summary' => $summary,
            'by_station' => $byStation,
            'rows' => $dailyRows,
        ]);
    }

    public function transactions(FinanceTransactionIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = DB::table('finance_journal_entries as entries')
            ->leftJoin('stations', 'stations.id', '=', 'entries.station_id')
            ->leftJoin('customers', 'customers.id', '=', 'entries.customer_id')
            ->whereBetween('entries.entry_date', [$validated['date_from'], $validated['date_to']])
            ->when(
                $validated['station_id'] ?? null,
                fn ($builder, $stationId) => $builder->where('entries.station_id', $stationId)
            )
            ->when(
                $validated['source_type'] ?? null,
                fn ($builder, $sourceType) => $builder->where('entries.source_type', $sourceType)
            )
            ->when(
                $validated['status'] ?? null,
                fn ($builder, $status) => $builder->where('entries.status', $status)
            )
            ->orderByDesc('entries.entry_date')
            ->orderByDesc('entries.created_at')
            ->select([
                'entries.id',
                'entries.entry_no',
                'entries.entry_date',
                'entries.period_month',
                'entries.source_type',
                'entries.source_id',
                'entries.source_ref',
                'entries.station_id',
                'entries.customer_id',
                'entries.currency_code',
                'entries.status',
                'entries.memo',
                'entries.created_at',
                'stations.station_code',
                'stations.station_name',
                'customers.customer_whatsapp',
            ]);

        $paginated = $query->paginate((int) $validated['per_page']);
        $entryIds = collect($paginated->items())->pluck('id')->all();

        $lineMap = DB::table('finance_journal_lines as lines')
            ->join('finance_accounts as accounts', 'accounts.id', '=', 'lines.account_id')
            ->whereIn('lines.journal_entry_id', $entryIds)
            ->orderBy('lines.line_no')
            ->get([
                'lines.journal_entry_id',
                'lines.line_no',
                'lines.account_id',
                'accounts.account_code',
                'accounts.account_name',
                'lines.description',
                'lines.debit',
                'lines.credit',
            ])
            ->groupBy('journal_entry_id');

        $rows = collect($paginated->items())
            ->map(function ($entry) use ($lineMap): array {
                $entryLines = collect($lineMap->get($entry->id, []))
                    ->map(fn ($line): array => [
                        'line_no' => (int) $line->line_no,
                        'account_id' => (string) $line->account_id,
                        'account_code' => $line->account_code,
                        'account_name' => $line->account_name,
                        'description' => $line->description,
                        'debit' => round((float) $line->debit, 2),
                        'credit' => round((float) $line->credit, 2),
                    ])
                    ->values();

                $totalDebit = round((float) $entryLines->sum('debit'), 2);
                $totalCredit = round((float) $entryLines->sum('credit'), 2);

                return [
                    'id' => (string) $entry->id,
                    'entry_no' => $entry->entry_no,
                    'entry_date' => $entry->entry_date,
                    'period_month' => $entry->period_month,
                    'source_type' => $entry->source_type,
                    'source_id' => $entry->source_id ? (string) $entry->source_id : null,
                    'source_ref' => $entry->source_ref,
                    'station_id' => $entry->station_id ? (string) $entry->station_id : null,
                    'station_code' => $entry->station_code,
                    'station_name' => $entry->station_name,
                    'customer_id' => $entry->customer_id ? (string) $entry->customer_id : null,
                    'customer_whatsapp' => $entry->customer_whatsapp,
                    'currency_code' => $entry->currency_code,
                    'status' => $entry->status,
                    'memo' => $entry->memo,
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'is_balanced' => abs($totalDebit - $totalCredit) < 0.0001,
                    'lines' => $entryLines,
                    'created_at' => $entry->created_at,
                ];
            })
            ->values();

        return response()->json([
            'filters' => [
                'date_from' => $validated['date_from'],
                'date_to' => $validated['date_to'],
                'station_id' => $validated['station_id'] ?? null,
                'source_type' => $validated['source_type'] ?? null,
                'status' => $validated['status'] ?? null,
                'per_page' => (int) $validated['per_page'],
            ],
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'rows' => $rows,
        ]);
    }

    public function expenses(FinanceExpenseIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = DB::table('finance_expenses as expenses')
            ->leftJoin('stations', 'stations.id', '=', 'expenses.station_id')
            ->whereBetween('expenses.incurred_at', [$validated['date_from'], $validated['date_to']])
            ->when(
                $validated['station_id'] ?? null,
                fn ($builder, $stationId) => $builder->where('expenses.station_id', $stationId)
            )
            ->when(
                $validated['category_code'] ?? null,
                fn ($builder, $categoryCode) => $builder->where('expenses.category_code', $categoryCode)
            )
            ->when(
                $validated['status'] ?? null,
                fn ($builder, $status) => $builder->where('expenses.status', $status)
            )
            ->orderByDesc('expenses.incurred_at')
            ->orderByDesc('expenses.created_at')
            ->select([
                'expenses.id',
                'expenses.expense_no',
                'expenses.station_id',
                'stations.station_code',
                'stations.station_name',
                'expenses.category_code',
                'expenses.category_name',
                'expenses.vendor_name',
                'expenses.description',
                'expenses.amount_subtotal',
                'expenses.amount_tax',
                'expenses.amount_total',
                'expenses.currency_code',
                'expenses.incurred_at',
                'expenses.paid_at',
                'expenses.payment_method',
                'expenses.payment_ref',
                'expenses.status',
                'expenses.created_at',
            ]);

        $paginated = $query->paginate((int) $validated['per_page']);

        $rows = collect($paginated->items())
            ->map(fn ($expense): array => [
                'id' => (string) $expense->id,
                'expense_no' => $expense->expense_no,
                'station_id' => $expense->station_id ? (string) $expense->station_id : null,
                'station_code' => $expense->station_code,
                'station_name' => $expense->station_name,
                'category_code' => $expense->category_code,
                'category_name' => $expense->category_name,
                'vendor_name' => $expense->vendor_name,
                'description' => $expense->description,
                'amount_subtotal' => round((float) $expense->amount_subtotal, 2),
                'amount_tax' => round((float) $expense->amount_tax, 2),
                'amount_total' => round((float) $expense->amount_total, 2),
                'currency_code' => $expense->currency_code,
                'incurred_at' => $expense->incurred_at,
                'paid_at' => $expense->paid_at,
                'payment_method' => $expense->payment_method,
                'payment_ref' => $expense->payment_ref,
                'status' => $expense->status,
                'created_at' => $expense->created_at,
            ])
            ->values();

        $stations = DB::table('stations')
            ->orderBy('station_code')
            ->get(['id', 'station_code', 'station_name'])
            ->map(fn ($station): array => [
                'id' => (string) $station->id,
                'station_code' => $station->station_code,
                'station_name' => $station->station_name,
            ])
            ->values();

        return response()->json([
            'filters' => [
                'date_from' => $validated['date_from'],
                'date_to' => $validated['date_to'],
                'station_id' => $validated['station_id'] ?? null,
                'category_code' => $validated['category_code'] ?? null,
                'status' => $validated['status'] ?? null,
                'per_page' => (int) $validated['per_page'],
            ],
            'options' => [
                'categories' => $this->manualExpensePostingService->categoryOptions(),
                'payment_methods' => $this->manualExpensePostingService->paymentMethodOptions(),
                'stations' => $stations,
            ],
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'rows' => $rows,
        ]);
    }

    public function storeExpense(FinanceExpenseStoreRequest $request): JsonResponse
    {
        $expense = $this->manualExpensePostingService->createAndPost(
            payload: $request->validated(),
            userId: $request->user()?->id
        );

        return response()->json([
            'message' => 'Expense berhasil disimpan dan dijurnal.',
            'expense' => $expense,
        ], 201);
    }
}
