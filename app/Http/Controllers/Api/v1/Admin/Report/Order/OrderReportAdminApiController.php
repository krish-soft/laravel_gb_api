<?php

namespace App\Http\Controllers\Api\v1\Admin\Report\Order;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Buyer\Order\Order;
use App\Models\Buyer\Order\OrderItem;
use App\Models\Buyer\Order\OrderCharge;
use App\Models\Master\Depot\MstDepot;
use Illuminate\Http\Request;
use Carbon\Carbon;

class OrderReportAdminApiController extends ApiResponseWithAdminAuthController
{


    public function getOrdersReportByDepot(Request $request)
    {
        $request->validate([
            'depot_id'   => 'required|exists:mst_depots,id',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
        ]);

        /* =========================
       DATE RANGE (BUSINESS DATE)
    ========================= */
        $from = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->toDateString()
            : now()->subDays(30)->toDateString();

        $to = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->toDateString()
            : now()->toDateString();

        /* =========================
       STATUS GROUPS (ONLY 3)
    ========================= */
        $openStatuses       = ['pending'];
        $processingStatuses = ['processing'];
        $closedStatuses     = ['shipped', 'completed'];
        $allowedStatuses    = array_merge(
            $openStatuses,
            $processingStatuses,
            $closedStatuses
        );

        $depot = MstDepot::findOrFail($request->depot_id);

        /* =========================
       LOAD ORDERS (SOURCE OF TRUTH)
    ========================= */
        $orders = Order::where('depot_id', $request->depot_id)
            ->whereDate('order_date', '>=', $from)   // ✅ FIX
            ->whereDate('order_date', '<=', $to)     // ✅ FIX
            ->whereIn('order_status', $allowedStatuses)
            ->get();

        /* =========================
       LOAD ITEMS
    ========================= */
        $items = OrderItem::with('order')
            ->whereHas('order', function ($q) use ($request, $from, $to, $allowedStatuses) {
                $q->where('depot_id', $request->depot_id)
                    ->whereDate('order_date', '>=', $from)   // ✅ FIX
                    ->whereDate('order_date', '<=', $to)     // ✅ FIX
                    ->whereIn('order_status', $allowedStatuses);
            })
            ->get();

        /* =========================
       ORDERS SUMMARY
    ========================= */
        $ordersSummary = [
            'total_orders'      => $orders->count(),
            'open_orders'       => $orders->whereIn('order_status', $openStatuses)->count(),
            'processing_orders' => $orders->whereIn('order_status', $processingStatuses)->count(),
            'closed_orders'     => $orders->whereIn('order_status', $closedStatuses)->count(),
        ];

        /* =========================
       QUANTITY SUMMARY
    ========================= */
        $orderedQty = (int) $items->sum('order_qty');

        $shippedQty = (int) $items
            ->filter(fn($i) => in_array($i->order->order_status, $closedStatuses))
            ->sum('ship_qty');

        $quantitySummary = [
            'ordered_qty'         => $orderedQty,
            'shipped_qty'         => $shippedQty,
            'pending_to_ship_qty' => $orderedQty - $shippedQty,
        ];

        /* =========================
       AMOUNT SUMMARY
    ========================= */
        $amountSummary = [
            'open_amount' =>
            (float) $items->filter(
                fn($i) =>
                in_array($i->order->order_status, $openStatuses)
            )->sum('total_amount'),

            'processing_amount' =>
            (float) $items->filter(
                fn($i) =>
                in_array($i->order->order_status, $processingStatuses)
            )->sum('total_amount'),

            'closed_amount' =>
            (float) $items->filter(
                fn($i) =>
                in_array($i->order->order_status, $closedStatuses)
            )->sum('total_amount'),
        ];

        $amountSummary['total_amount'] =
            $amountSummary['open_amount']
            + $amountSummary['processing_amount']
            + $amountSummary['closed_amount'];

        /* =========================
       RESPONSE
    ========================= */
        return $this->successResponse('success', [
            'meta' => [
                'from'     => $from,
                'to'       => $to,
                'depot_id' => (int) $request->depot_id,
            ],
            'depot' => $depot,
            'orders_summary'   => $ordersSummary,
            'quantity_summary' => $quantitySummary,
            'amount_summary'   => $amountSummary,
        ]);
    }

    //
}
