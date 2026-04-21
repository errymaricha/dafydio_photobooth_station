<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Models\PhotoSession;
use App\Models\Printer;
use App\Models\PrintLog;
use App\Models\PrintOrder;
use App\Models\PrintQueueJob;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();
        $sevenDaysAgo = now()->subDays(6)->toDateString();

        $recentSessions = PhotoSession::with(['station', 'device'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'session_code' => $session->session_code,
                    'status' => $session->status,
                    'captured_count' => $session->captured_count,
                    'completed_at' => $session->completed_at,
                    'station' => [
                        'id' => $session->station?->id,
                        'code' => $session->station?->station_code,
                    ],
                    'device' => [
                        'id' => $session->device?->id,
                        'code' => $session->device?->device_code,
                    ],
                ];
            })
            ->values();

        $recentPrintOrders = PrintOrder::with(['session', 'printer'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_code' => $order->order_code,
                    'status' => $order->status,
                    'total_qty' => $order->total_qty,
                    'total_amount' => $order->total_amount,
                    'ordered_at' => $order->ordered_at,
                    'session' => [
                        'id' => $order->session?->id,
                        'session_code' => $order->session?->session_code,
                    ],
                    'printer' => [
                        'id' => $order->printer?->id,
                        'name' => $order->printer?->printer_name,
                    ],
                ];
            })
            ->values();

        $recentFailedJobs = PrintQueueJob::with(['printOrder.session', 'printer'])
            ->where('status', 'failed')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'last_error' => $job->last_error,
                    'finished_at' => $job->finished_at,
                    'print_order' => [
                        'id' => $job->printOrder?->id,
                        'order_code' => $job->printOrder?->order_code,
                    ],
                    'session' => [
                        'id' => $job->printOrder?->session?->id,
                        'session_code' => $job->printOrder?->session?->session_code,
                    ],
                    'printer' => [
                        'id' => $job->printer?->id,
                        'name' => $job->printer?->printer_name,
                    ],
                ];
            })
            ->values();

        $recentLogs = PrintLog::with(['printOrder', 'printer'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'log_level' => $log->log_level,
                    'message' => $log->message,
                    'created_at' => $log->created_at,
                    'print_order' => [
                        'id' => $log->printOrder?->id,
                        'order_code' => $log->printOrder?->order_code,
                    ],
                    'printer' => [
                        'id' => $log->printer?->id,
                        'name' => $log->printer?->printer_name,
                    ],
                ];
            })
            ->values();

        $printerBase = Printer::query();

        $financeTodayRaw = DB::table('finance_journal_entries as entries')
            ->join('finance_journal_lines as lines', 'lines.journal_entry_id', '=', 'entries.id')
            ->join('finance_accounts as accounts', 'accounts.id', '=', 'lines.account_id')
            ->where('entries.entry_date', $today)
            ->selectRaw("coalesce(sum(case when accounts.account_type = 'revenue' then lines.credit - lines.debit else 0 end), 0) as revenue_amount")
            ->selectRaw("coalesce(sum(case when accounts.account_type = 'expense' then lines.debit - lines.credit else 0 end), 0) as expense_amount")
            ->first();

        $financeTodayRevenue = round((float) ($financeTodayRaw->revenue_amount ?? 0), 2);
        $financeTodayExpense = round((float) ($financeTodayRaw->expense_amount ?? 0), 2);
        $financeTodayProfit = round($financeTodayRevenue - $financeTodayExpense, 2);

        $financeLastSevenDays = DB::table('finance_journal_entries as entries')
            ->join('finance_journal_lines as lines', 'lines.journal_entry_id', '=', 'entries.id')
            ->join('finance_accounts as accounts', 'accounts.id', '=', 'lines.account_id')
            ->whereBetween('entries.entry_date', [$sevenDaysAgo, $today])
            ->groupBy('entries.entry_date')
            ->orderBy('entries.entry_date')
            ->get([
                'entries.entry_date',
                DB::raw("coalesce(sum(case when accounts.account_type = 'revenue' then lines.credit - lines.debit else 0 end), 0) as revenue_amount"),
                DB::raw("coalesce(sum(case when accounts.account_type = 'expense' then lines.debit - lines.credit else 0 end), 0) as expense_amount"),
            ])
            ->map(function ($row): array {
                $revenue = round((float) $row->revenue_amount, 2);
                $expense = round((float) $row->expense_amount, 2);
                $profit = round($revenue - $expense, 2);

                return [
                    'entry_date' => $row->entry_date,
                    'revenue_amount' => $revenue,
                    'expense_amount' => $expense,
                    'gross_profit_amount' => $profit,
                    'net_profit_amount' => $profit,
                ];
            })
            ->values();

        return response()->json([
            'sessions' => [
                'uploaded_today' => PhotoSession::where('status', 'uploaded')
                    ->whereDate('completed_at', $today)
                    ->count(),
                'editing' => PhotoSession::where('status', 'editing')->count(),
                'ready_print' => PhotoSession::where('status', 'ready_print')->count(),
                'queued_print' => PhotoSession::where('status', 'queued_print')->count(),
                'failed_print' => PhotoSession::where('status', 'failed_print')->count(),
                'printed_today' => PhotoSession::where('status', 'printed')
                    ->whereDate('updated_at', $today)
                    ->count(),
            ],

            'print_orders' => [
                'today' => PrintOrder::whereDate('ordered_at', $today)->count(),
                'queued' => PrintOrder::where('status', 'queued')->count(),
                'printing' => PrintOrder::where('status', 'printing')->count(),
                'failed' => PrintOrder::where('status', 'failed')->count(),
                'printed_today' => PrintOrder::where('status', 'printed')
                    ->whereDate('completed_at', $today)
                    ->count(),
            ],

            'print_queue' => [
                'pending' => PrintQueueJob::where('status', 'pending')->count(),
                'processing' => PrintQueueJob::where('status', 'processing')->count(),
                'failed' => PrintQueueJob::where('status', 'failed')->count(),
                'completed_today' => PrintQueueJob::where('status', 'completed')
                    ->whereDate('finished_at', $today)
                    ->count(),
            ],

            'printers' => [
                'total' => (clone $printerBase)->count(),
                'online' => (clone $printerBase)
                    ->whereNotNull('last_seen_at')
                    ->where('last_seen_at', '>', now()->subMinutes(2))
                    ->count(),
                'offline' => (clone $printerBase)
                    ->where(function ($query) {
                        $query->whereNull('last_seen_at')
                            ->orWhere('last_seen_at', '<=', now()->subMinutes(2));
                    })
                    ->count(),
                'ready' => (clone $printerBase)->where('status', 'ready')->count(),
                'printing' => (clone $printerBase)->where('status', 'printing')->count(),
                'error' => (clone $printerBase)->where('status', 'error')->count(),
            ],
            'finance' => [
                'today' => [
                    'revenue_amount' => $financeTodayRevenue,
                    'expense_amount' => $financeTodayExpense,
                    'gross_profit_amount' => $financeTodayProfit,
                    'net_profit_amount' => $financeTodayProfit,
                ],
                'last_7_days' => $financeLastSevenDays,
            ],

            'recent_sessions' => $recentSessions,
            'recent_print_orders' => $recentPrintOrders,
            'recent_failed_jobs' => $recentFailedJobs,
            'recent_logs' => $recentLogs,
        ]);
    }
}
