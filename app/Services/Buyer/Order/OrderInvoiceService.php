<?php

namespace App\Services\Buyer\Order;

use App\Models\Buyer\Order\Order;
use App\Models\Buyer\Order\OrderInvoice;
use App\Models\Common\Address;
use App\Models\Master\Setting\MstBusinessSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderInvoiceService
{
    /**
     * INDUSTRY SAFE:
     * - If invoice exists → ensure PDF exists
     * - If missing file → rebuild PDF
     * - Else create new invoice
     */
    public function generateInvoiceForOrder(Order $order, bool $isEnforce = false): OrderInvoice
    {
        return DB::transaction(function () use ($order, $isEnforce) {

            $order->load([
                'orderInvoice',
                'buyer',
            ]);

            // =========================
            // INVOICE EXISTS → CHECK FILE
            // =========================

            if ($order->orderInvoice) {

                $invoice = $order->orderInvoice;

                if (
                    !$invoice->invoice_path ||
                    !Storage::disk('private')->exists($invoice->invoice_path)
                    || $isEnforce
                ) {
                    return $this->rebuildPdf($invoice, $order);
                }

                return $invoice;
            }

            // =========================
            // CREATE NEW INVOICE
            // =========================

            $invoice = OrderInvoice::create([
                'order_id'     => $order->id,
                'invoice_date' => now(),
                'invoice_path' => null,
            ]);

            return $this->rebuildPdf($invoice, $order);
        });
    }

    /**
     * Rebuild only PDF file
     */
    public function rebuildPdf(OrderInvoice $invoice, ?Order $order = null): OrderInvoice
    {
        return DB::transaction(function () use ($invoice, $order) {

            $order = $order ?: $invoice->order;

            $order->load([
                'orderItems.productListingItem.productListing',
                'orderCharges',
                'buyer',
                'shippingFulfillmentLocation.address',
                'payment',
            ]);

            $buyer = $order->buyer;

            // ================= BILLING ADDRESS =================

            $billingAddress = Address::where(
                'addr_code',
                $buyer?->addr_code
            )->first();

            // if not then try bill_addr_code
            if (!$billingAddress && $order->bill_addr_code) {
                $billingAddress = Address::where(
                    'addr_code',
                    $order->bill_addr_code
                )->first();
            }

            // ================= SHIPPING ADDRESS =================

            $shippingAddress = $order->shippingFulfillmentLocation?->address;

            // see ship address empty and bill address not then use bill address as ship address (some buyer use same address for billing and shipping)
            if (!$shippingAddress && $billingAddress) {
                $shippingAddress = $billingAddress;
            }

            // Still billAddress empty then use $shippingAddress as billing address (some buyer use same address for billing and shipping)
            if (!$billingAddress) {
                $billingAddress = $shippingAddress;
            }

            // ================= BUSINESS SETTINGS =================

            $business = MstBusinessSetting::getOrCreate()
                ->load(['billAddress', 'address']);

            // ================= GENERATE PDF =================

            $pdf = Pdf::loadView(
                'pdf.order_invoice',
                [
                    'order'           => $order,
                    'invoice'         => $invoice,
                    'business'        => $business,
                    'billingAddress'  => $billingAddress,
                    'shippingAddress' => $shippingAddress,
                ]
            )->setPaper('a4', 'portrait');

            // ================= STORE FILE =================

            $path = $this->buildPdfPath($buyer->user_code, $invoice->invoice_number);

            Storage::disk('private')->put($path, $pdf->output());

            $invoice->update([
                'invoice_path' => $path,
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * Helper to generate file path
     */
    protected function buildPdfPath(string $userCode, string $invoiceNumber): string
    {
        return "invoices/{$userCode}/{$invoiceNumber}.pdf";
    }
}
