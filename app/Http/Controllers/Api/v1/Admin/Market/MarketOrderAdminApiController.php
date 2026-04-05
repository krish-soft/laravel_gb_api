<?php

namespace App\Http\Controllers\Api\v1\Admin\Market;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Market\MarketOrder;
use App\Models\Market\MarketOrderDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MarketOrderAdminApiController extends  ApiResponseWithAdminAuthController
{
    //



    public function getOrdersList(Request $request)
    {
        //

        $orderQuery = MarketOrder::with(['market.fulfillmentLocation.address', 'depot', 'shippingFulfillmentLocation.address'])->latest();

        if ($request->has('status')) {
            $orderQuery->where('status', $request->input('status'));
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $orderQuery->whereBetween('order_date', [$request->input('start_date'), $request->input('end_date')]);
        }

        $orders = $orderQuery->get();


        return $this->successResponse(__('messages.success_messages.success_get'), $orders, 200);
    }



    public function getOrderDetails($orderId)
    {
        //

        $order = MarketOrder::with([
            'market.fulfillmentLocation.address',
            'marketOrderItems.pickupFulfillmentLocation',

            'marketOrderDocuments',
            'shippingFulfillmentLocation.address', // actual shipping location        

            // shipment packages for this order           
            'shipmentPackages.shipment', // to get shipment details like type, status, etc.

        ])->where('id', $orderId)->firstOrfail();



        return $this->successResponse(__('messages.success_messages.success_get'), $order, 200);
    }


    // Uplaod order documents, update order status, etc. can be added here as needed

    public function uploadOrderDocument(Request $request, $marketOrderId)
    {


        $request->validate([
            'document_file' => 'required|image|mimes:jpg,jpeg,png|max:2048', // adjust as needed
            'document_type' => 'required|string|max:30|in:invoice,receipt,other', // example document types
        ]);

        $marketOrder = MarketOrder::findOrFail($marketOrderId);

        $file = $request->file('document_file');
        $filename = time() . '_' . $file->getClientOriginalName();

        $filePath = uploadPrivateFile(
            $file,
            "market_orders/{$marketOrder->market_order_number}", // no end slash, store in a folder specific to the order
            null,
            false
        );

        // You can save the file path and document type to the database if needed
        $marketOrder->marketOrderDocuments()->create([
            'market_order_id' => $marketOrder->id,
            'document_path' => $filePath,
            'document_type' => $request->input('document_type'),
        ]);

        // Log activity
        logActivity(
            'mkt_order_document_uploaded',        // EVENT
            $request->user(),                 // ACTOR (who did it)
            get_class($marketOrder), // SUBJECT TYPE (what was affected)
            $marketOrder->id,              // SUBJECT ID
            $marketOrder->market_order_number,       // SUBJECT CODE (human readable)
            [
                $marketOrder->market_order_number,
                'document_type' => $request->input('document_type'),
            ]
        );



        return $this->successResponse(__('messages.success_messages.success_upload'), null, 200);
    }

    // Delete document, update order status, etc. can be added here as needed

    public function deleteOrderDocument(Request $request, $marketOrderDocumentId)
    {
        $marketOrderDocument = MarketOrderDocument::findOrFail($marketOrderDocumentId);

        // Delete the file from storage
        if ($marketOrderDocument->document_path) {
            Storage::disk('private')->delete($marketOrderDocument->document_path);
        }

        logActivity(
            'mkt_order_document_deleted',        // EVENT
            $request->user(),                 // ACTOR (who did it)
            get_class($marketOrderDocument), // SUBJECT TYPE (what was affected)
            $marketOrderDocument->id,              // SUBJECT ID
            $marketOrderDocument->marketOrder->market_order_number,       // SUBJECT CODE (human readable)
            [
                'market_order_number' => $marketOrderDocument->marketOrder->market_order_number,
                'document_type' => $marketOrderDocument->document_type,
            ]
        );


        // Delete the document record from database
        $marketOrderDocument->delete();


        return $this->successResponse(__('messages.success_messages.success_delete'), null, 200);
    }



    // upate order status, etc. can be added here as needed

    public function updateOrderStatus(Request $request, MarketOrder $marketOrder)
    {
        $request->validate([
            'order_status' => 'required|string|max:30|in:' . implode(',', OrderStatusEnum::casesAsValues()), // example statuses
            'delivery_status' => 'nullable|string|max:30|in:' . implode(',', OrderStatusEnum::casesAsValues()), // example delivery statuses
        ]);

        $marketOrder->order_status = $request->input('order_status');
        $marketOrder->delivery_status = $request->input('delivery_status');

        $marketOrder->save();

        // Log activity
        logActivity(
            'mkt_order_status_updated',        // EVENT
            $request->user(),                 // ACTOR (who did it)
            get_class($marketOrder), // SUBJECT TYPE (what was affected)
            $marketOrder->id,              // SUBJECT ID
            $marketOrder->market_order_number,       // SUBJECT CODE (human readable)
            [
                'order_status' => $request->input('order_status'),
                'delivery_status' => $request->input('delivery_status'),
            ]
        );

        return $this->successResponse(__('messages.success_messages.success_update'), null, 200);
    }

    // Once sell on market 
    // another day we will receive that receipt and base on it we have to enter manually
    public function updateOrderAmountData(Request $request, $marketOrderId)
    {
        $request->validate([
            'subtotal'     => 'required|numeric|min:0',
            'tax_amount'   => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
        ]);

        $marketOrder = MarketOrder::findOrFail($marketOrderId);

        // ---------------------------------------
        // INPUT VALUES
        // ---------------------------------------
        $subtotalInput    = (float) ($request->input('subtotal') ?? 0);
        $taxAmountInput   = (float) ($request->input('tax_amount') ?? 0);
        $totalAmountInput = (float) ($request->input('total_amount') ?? 0);

        /*
    |--------------------------------------------------------------------------
    | CORE RULES
    |--------------------------------------------------------------------------
    | 1. Farmer products → TAX NOT DISTRIBUTED TO ITEMS
    | 2. Items calculated only from SUBTOTAL
    | 3. If subtotal missing → use total_amount as base
    |--------------------------------------------------------------------------
    */

        // decide base amount for item distribution
        $baseAmount = $subtotalInput > 0
            ? $subtotalInput
            : $totalAmountInput;

        // save header values
        $marketOrder->subtotal     = $subtotalInput;
        $marketOrder->tax_amount   = $taxAmountInput; // invoice level only
        $marketOrder->total_amount = $totalAmountInput;
        $marketOrder->save();

        // ---------------------------------------
        // DISTRIBUTE ONLY SUBTOTAL TO ITEMS
        // ---------------------------------------
        $items    = $marketOrder->marketOrderItems()->get();
        $totalQty = (float) $items->sum('ship_qty');

        if ($totalQty > 0 && $items->count() > 0 && $baseAmount > 0) {

            $runningAmount = 0;
            $lastIndex     = $items->count() - 1;

            foreach ($items->values() as $i => $item) {

                // $ratio = $item->order_qty / $totalQty;
                $ratio = $item->ship_qty / $totalQty;

                // last item handles rounding balance
                if ($i === $lastIndex) {

                    $itemAmount = round($baseAmount - $runningAmount, 2);
                } else {

                    $itemAmount = round($baseAmount * $ratio, 2);
                    $runningAmount += $itemAmount;
                }

                /*
            |--------------------------------------------------------------------------
            | IMPORTANT CHANGE
            |--------------------------------------------------------------------------
            | tax_amount = 0 (farmer raw products)
            | taxable_amount = distributed subtotal
            |--------------------------------------------------------------------------
            */

                $item->taxable_amount = $itemAmount;
                $item->tax_amount     = 0;      // 🔥 FIXED
                $item->total_amount   = $itemAmount; // no tax at item level
                $item->save();
            }
        }

        // ---------------------------------------
        // ACTIVITY LOG
        // ---------------------------------------
        logActivity(
            'mkt_order_amount_updated',
            $request->user(),
            get_class($marketOrder),
            $marketOrder->id,
            $marketOrder->market_order_number,
            [
                'subtotal'     => $subtotalInput,
                'tax_amount'   => $taxAmountInput,
                'total_amount' => $totalAmountInput,
            ]
        );

        return $this->successResponse(__('messages.success_messages.success_update'), null, 200);
    }



    //
}
