<?php

namespace App\Http\Controllers\Api\Editor;

use App\Http\Controllers\Controller;
use App\Models\PrintOrder;
use App\Models\PrintOrderItem;
use App\Models\RenderedOutput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Http\Requests\CreatePrintOrderRequest;

class PrintOrderController extends Controller
{
    public function store(CreatePrintOrderRequest $request, RenderedOutput $renderedOutput)
    {
        $existingOrderItem = PrintOrderItem::with('printOrder')
            ->where('rendered_output_id', $renderedOutput->id)
            ->whereHas('printOrder', function ($query) {
                $query->whereIn('status', ['created', 'queued', 'printing']);
            })
            ->first();

        if ($existingOrderItem) {
            return response()->json([
                'message' => 'Print order already exists for this rendered output',
                'print_order_id' => $existingOrderItem->printOrder?->id,
                'order_code' => $existingOrderItem->printOrder?->order_code,
                'status' => $existingOrderItem->printOrder?->status,
            ], 200);
        }

        $renderedOutput->load(['session']);

        if (!$renderedOutput->session) {
            return response()->json([
                'message' => 'Rendered output session not found.'
            ], 422);
        }

        $copies = (int) $request->input('copies', 1);
        $unitPrice = (float) $request->input('unit_price', 0);
        $lineTotal = $copies * $unitPrice;

        [$printOrder, $printOrderItem] = DB::transaction(function () use ($copies, $lineTotal, $renderedOutput, $request, $unitPrice) {
            $printOrder = PrintOrder::create([
                'id' => (string) Str::uuid(),
                'order_code' => 'PO-' . strtoupper(Str::random(8)),
                'session_id' => $renderedOutput->session_id,
                'user_id' => null,
                'station_id' => $renderedOutput->session->station_id,
                'printer_id' => $request->input('printer_id'),
                'source_type' => 'admin_panel',
                'order_type' => 'session_print',
                'payment_status' => 'unpaid',
                'total_items' => 1,
                'total_qty' => $copies,
                'subtotal_amount' => $lineTotal,
                'discount_amount' => 0,
                'total_amount' => $lineTotal,
                'status' => 'created',
                'ordered_at' => now(),
            ]);

            $printOrderItem = PrintOrderItem::create([
                'id' => (string) Str::uuid(),
                'print_order_id' => $printOrder->id,
                'rendered_output_id' => $renderedOutput->id,
                'session_photo_id' => null,
                'file_id' => $renderedOutput->file_id,
                'paper_size' => $request->input('paper_size', '4R'),
                'copies' => $copies,
                'print_layout' => 'single',
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'status' => 'created',
            ]);

            return [$printOrder, $printOrderItem];
        });

        return response()->json([
            'message' => 'Print order created',
            'print_order_id' => $printOrder->id,
            'order_code' => $printOrder->order_code,
            'print_order_item_id' => $printOrderItem->id,
            'status' => $printOrder->status,
            'session_status' => $renderedOutput->session->fresh()->status,
        ], 201);
    }

    public function index()
    {
        $orders = PrintOrder::with([
            'session',
            'printer',
            'items.renderedOutput',
        ])
            ->latest()
            ->limit(20)
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
                        'status' => $order->printer?->status,
                    ],
                ];
            });

        return response()->json($orders);
    }

    public function show(PrintOrder $printOrder)
    {
        $printOrder->load([
            'session',
            'printer',
            'items.renderedOutput.file',
        ]);

        return response()->json([
            'id' => $printOrder->id,
            'order_code' => $printOrder->order_code,
            'status' => $printOrder->status,
            'payment_status' => $printOrder->payment_status,
            'total_items' => $printOrder->total_items,
            'total_qty' => $printOrder->total_qty,
            'subtotal_amount' => $printOrder->subtotal_amount,
            'discount_amount' => $printOrder->discount_amount,
            'total_amount' => $printOrder->total_amount,
            'ordered_at' => $printOrder->ordered_at,

            'session' => [
                'id' => $printOrder->session?->id,
                'session_code' => $printOrder->session?->session_code,
            ],

            'printer' => [
                'id' => $printOrder->printer?->id,
                'name' => $printOrder->printer?->printer_name,
                'status' => $printOrder->printer?->status,
            ],

            'items' => $printOrder->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'copies' => $item->copies,
                    'paper_size' => $item->paper_size,
                    'status' => $item->status,
                    'file_url' => $item->renderedOutput?->file
                        ? url('storage/' . $item->renderedOutput->file->file_path)
                        : null,
                ];
            }),
        ]);
    }
}
