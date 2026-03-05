<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\Invoice;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Common\Invoice\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceAdminApiController extends ApiResponseWithAdminAuthController
{

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'invoice_type' => 'nullable|in:sale,refund,return,delivery',
            'payment_status' => 'nullable|in:pending,paid,overdue',
        ]);


        $startDate = $request->filled('start_date') ? $request->start_date : now()->subDay()->toDateString();
        $endDate = $request->filled('end_date') ? $request->end_date : now()->toDateString();



        $invoices = Invoice::latest()
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->when($request->filled('invoice_type'), function ($query) use ($request) {
                $query->where('invoice_type', $request->invoice_type);
            })
            ->when($request->filled('payment_status'), function ($query) use ($request) {
                $query->where('payment_status', $request->payment_status);
            })
            ->orderBy('invoice_date', 'desc')
            ->get();


        return $this->showSuccessMessage(__('messages.success_messages.success_get'), 200, $invoices);

        //
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'user_id'      => 'required|exists:users,id',
            'invoice_date' => 'required|date',
            'invoice_type' => 'required|in:sale,refund,return,delivery',

            'items'                      => 'required|array|min:1',
            'items.*.item_name'          => 'required|string',
            'items.*.order_qty'          => 'required|numeric|min:0.01',
            'items.*.taxable_amount'     => 'required|numeric|min:0',
            'items.*.tax_amount'         => 'required|numeric|min:0',

            'charges'                    => 'nullable|array',
            'charges.*.charge_name'      => 'required_with:charges|string',
            'charges.*.taxable_amount'   => 'required_with:charges|numeric|min:0',
            'charges.*.tax_amount'       => 'required_with:charges|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {

            $baseAmount = 0;               // only item taxable
            $chargeTaxableTotal = 0;       // only charge taxable
            $totalTax = 0;                 // item tax + charge tax

            $invoice = Invoice::create([
                'user_id'        => $request->user_id,
                'invoice_date'   => $request->invoice_date,
                'invoice_type'   => $request->invoice_type,
                'status'         => 'draft',
                'payment_status' => 'pending',
                'currency'       => 'INR',
                'is_locked'      => false,
            ]);

            // ITEMS
            foreach ($request->items as $item) {

                $itemTotal = $item['taxable_amount'] + $item['tax_amount'];

                $invoice->invoiceItems()->create([
                    'source_id'       => $item['source_id'] ?? null,
                    'item_code'       => $item['item_code'] ?? null,
                    'item_name'       => $item['item_name'],
                    'order_qty'       => $item['order_qty'],
                    'ship_qty'        => $item['ship_qty'] ?? $item['order_qty'],
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'taxable_amount'  => $item['taxable_amount'],
                    'tax_amount'      => $item['tax_amount'],
                    'total_amount'    => $itemTotal,
                    'reference'       => $item['reference'] ?? null,
                ]);

                $baseAmount += $item['taxable_amount'];
                $totalTax   += $item['tax_amount'];
            }

            // CHARGES
            if ($request->filled('charges')) {

                foreach ($request->charges as $charge) {

                    $chargeTotal = $charge['taxable_amount'] + $charge['tax_amount'];

                    $invoice->invoiceCharges()->create([
                        'charge_name'    => $charge['charge_name'],
                        'qty'            => $charge['qty'] ?? 1,
                        'ship_qty'       => $charge['ship_qty'] ?? 1,
                        'taxable_amount' => $charge['taxable_amount'],
                        'tax_amount'     => $charge['tax_amount'],
                        'total_amount'   => $chargeTotal,
                    ]);

                    $chargeTaxableTotal += $charge['taxable_amount'];
                    $totalTax           += $charge['tax_amount'];
                }
            }

            $subtotal = $baseAmount + $chargeTaxableTotal;
            $totalAmount = $subtotal + $totalTax;

            $invoice->update([
                'base_amount'  => $baseAmount,
                'tax_amount'   => $totalTax,
                'subtotal'     => $subtotal,
                'total_amount' => $totalAmount,
            ]);

            DB::commit();

            return $this->showSuccessMessage(__('messages.success_messages.success_create'), 200);
        } catch (\Exception $e) {

            DB::rollBack();
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $invoice = Invoice::with(['invoiceItems', 'invoiceCharges'])->findOrFail($id);

        if ($invoice->is_locked) {
            return $this->errorResponse(__('messages.error_messages.locked_resource'), 403);
        }

        $request->validate([
            'invoice_date' => 'required|date',

            'items'                      => 'required|array|min:1',
            'items.*.item_name'          => 'required|string',
            'items.*.order_qty'          => 'required|numeric|min:0.01',
            'items.*.taxable_amount'     => 'required|numeric|min:0',
            'items.*.tax_amount'         => 'required|numeric|min:0',

            'charges'                    => 'nullable|array',
            'charges.*.charge_name'      => 'required_with:charges|string',
            'charges.*.taxable_amount'   => 'required_with:charges|numeric|min:0',
            'charges.*.tax_amount'       => 'required_with:charges|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {

            $invoice->update([
                'invoice_date' => $request->invoice_date,
                'remarks'      => $request->remarks ?? null,
                'reference'    => $request->reference ?? null,
            ]);

            $invoice->invoiceItems()->delete();
            $invoice->invoiceCharges()->delete();

            $baseAmount = 0;
            $chargeTaxableTotal = 0;
            $totalTax = 0;

            // ITEMS
            foreach ($request->items as $item) {

                $itemTotal = $item['taxable_amount'] + $item['tax_amount'];

                $invoice->invoiceItems()->create([
                    'source_id'       => $item['source_id'] ?? null,
                    'item_code'       => $item['item_code'] ?? null,
                    'item_name'       => $item['item_name'],
                    'order_qty'       => $item['order_qty'],
                    'ship_qty'        => $item['ship_qty'] ?? $item['order_qty'],
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'taxable_amount'  => $item['taxable_amount'],
                    'tax_amount'      => $item['tax_amount'],
                    'total_amount'    => $itemTotal,
                    'reference'       => $item['reference'] ?? null,
                ]);

                $baseAmount += $item['taxable_amount'];
                $totalTax   += $item['tax_amount'];
            }

            // CHARGES
            if ($request->filled('charges')) {

                foreach ($request->charges as $charge) {

                    $chargeTotal = $charge['taxable_amount'] + $charge['tax_amount'];

                    $invoice->invoiceCharges()->create([
                        'charge_name'    => $charge['charge_name'],
                        'qty'            => $charge['qty'] ?? 1,
                        'ship_qty'       => $charge['ship_qty'] ?? 1,
                        'taxable_amount' => $charge['taxable_amount'],
                        'tax_amount'     => $charge['tax_amount'],
                        'total_amount'   => $chargeTotal,
                    ]);

                    $chargeTaxableTotal += $charge['taxable_amount'];
                    $totalTax           += $charge['tax_amount'];
                }
            }

            $subtotal = $baseAmount + $chargeTaxableTotal;
            $totalAmount = $subtotal + $totalTax;

            $invoice->update([
                'base_amount'  => $baseAmount,
                'tax_amount'   => $totalTax,
                'subtotal'     => $subtotal,
                'total_amount' => $totalAmount,
            ]);

            DB::commit();

            return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
        } catch (\Exception $e) {

            DB::rollBack();
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);

        // if ($invoice->is_locked) {
        //     return $this->errorResponse(__('messages.error_messages.locked_resource'), 403);
        // }

        return $this->errorResponse(__('messages.error_messages.locked_resource'), 403);

        $invoice->delete();

        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }
}
