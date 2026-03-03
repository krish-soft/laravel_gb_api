<?php

namespace App\Services\Invoice;

use App\Enum\Common\Invoice\InvoiceStatusEnum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Common\Accounting\Account;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Master\Setting\MstBusinessSetting;
use App\Models\Master\Setting\MstFinanceSetting;
use App\Models\Seller\Product\ProductListing;
use App\Services\Common\Charge\ChargeCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class InvoiceService
{

    // Order Invoice
    public function generateOrderInvoiceData(Order $order)
    {
        // Not PDF only Invoice data

        //       
        DB::transaction(function () use ($order) {

            $order->load([
                'buyer',
                'orderInvoices',
                'shipmentPackages',
                'orderCharges',
                'orderItems', // seller not to load 
            ]);

            $buyer = $order->buyer;
            $orderItems = $order->orderItems;
            $orderCharges = $order->orderCharges;
            $shipmentPackages = $order->shipmentPackages;

            // if not found throw runtime exception and it will be handled by job and can be retried later, we can also log the error for debugging
            if ($shipmentPackages->isEmpty() || $orderItems->isEmpty()) {
                throw new RuntimeException("No shipment packages or order items found for Order ID: {$order->id}");
            }

            $business = MstBusinessSetting::getOrCreate(); // Assuming you have a business settings table with necessary info

            // Invoice 
            $invoice = $order->orderInvoices()->create([
                'user_id' => $order->buyer_id,
                'order_id' => $order->id,

                'reference' => $order->order_number, // we can also have separate invoice reference if needed

                'invoice_date' => now(),
                'invoice_type' => InvoiceStatusEnum::SALES->value, // or 'proforma' based on your logic

                'status' => InvoiceStatusEnum::GENERATED->value, // or 'draft' based on your logic
                'payment_status' => $order->payment_status,

                'currency' => $order->currency,

                'platform_bill_addr_code' => $business->bill_addr_code ?? $business->addr_code, // fix of platform

                'customer_bill_addr_code' => $order->bill_addr_code, // Optional
                'customer_ship_addr_code' => $order->ship_addr_code, // need
            ]);


            // First Create a Invoice for each Order Item then base on shipment package delivered or not make reverse or final invoice
            foreach ($orderItems as $orderItem) {

                // Base on order create all 
                $invoice->invoiceItems()->create([
                    'product_code' => $orderItem->product_code,
                    'product_name' => $orderItem->product_name,
                    'order_qty' => $orderItem->order_qty,
                    'ship_qty' => $orderItem->ship_qty,
                    'pack_size' => $orderItem->pack_size,
                    'pack_unit' => $orderItem->pack_unit,
                    'pack_type_unit' => $orderItem->pack_type_unit,
                    'pack_price' => $orderItem->pack_price,
                    'discount_amount' => $orderItem->discount_amount,
                    'taxable_amount' => $orderItem->taxable_amount,
                    'tax_amount' => $orderItem->tax_amount,
                    'total_amount' => $orderItem->total_amount,
                ]);

                //
            }

            // We already have charges so no need to calculate
            // Charges same way
            foreach ($orderCharges as $orderCharge) {
                $invoice->invoiceCharges()->create([
                    'charge_name' => $orderCharge->charge_name,
                    'qty' => $orderCharge->qty ?? 1,
                    'ship_qty' => $orderCharge->ship_qty ?? 0,
                    'taxable_amount' => $orderCharge->taxable_amount,
                    'tax_amount' => $orderCharge->tax_amount,
                    'total_amount' => $orderCharge->total_amount,
                ]);

                //
            }

            // Now Check Each Shipment Package if delivered or not and make final invoice or reverse invoice, we can also have partial invoice if some packages delivered and some not, but for simplicity we will create only one invoice with all items and update the status based on shipment package status
            foreach ($shipmentPackages as $shipmentPackage) {

                $status = $shipmentPackage->status;
                $sellerStatus = $shipmentPackage->seller_status;

                if ($status == ShipmentStatusEnum::DELIVERED->value) {
                    continue;
                }

                // Now for undelivered pacakges

                if (in_array($sellerStatus, [
                    ShipmentStatusEnum::PENDING->value,
                    ShipmentStatusEnum::NOT_PICKED_UP->value,
                ])) {

                    // Now We need to refund that pacakge
                    $invoice->invoiceItems()->create([
                        'product_name' => "Undelivered Package:$shipmentPackage->shipment_package_number ($shipmentPackage->package_number)",
                        'order_qty' => -1 * $shipmentPackage->qty, // negative for refund
                        'ship_qty' => 0,
                        'pack_size' => $shipmentPackage->pack_size,
                        'pack_unit' => $shipmentPackage->pack_unit,
                        'pack_type_unit' => $shipmentPackage->pack_type_unit,
                        'pack_price' => $shipmentPackage->pack_price,
                        'discount_amount' => 0, // we can also calculate discount if needed
                        'taxable_amount' => -1 * $shipmentPackage->qty * $shipmentPackage->pack_price, // negative for refund
                        'tax_amount' => 0, // negative for refund
                        'total_amount' => -1 * ($shipmentPackage->qty * $shipmentPackage->pack_price), // negative for refund
                    ]);

                    // We need to reverse delivery charge
                    $deliveryChargeData = $this->getDeliveryCharge($buyer->charge_level_code, $shipmentPackage);

                    $invoice->invoiceCharges()->create([
                        'charge_name' => "Undelivered Package Delivery Charge: $shipmentPackage->shipment_package_number ($shipmentPackage->package_number)",
                        'qty' => -1 * $shipmentPackage->qty, // negative for refund
                        'ship_qty' => 0,
                        'taxable_amount' => -1 * $deliveryChargeData->charge_taxable, // negative for refund
                        'tax_amount' => -1 * $deliveryChargeData->charge_tax, // negative for refund
                        'total_amount' => -1 * $deliveryChargeData->total_charge_amount, // negative for refund
                    ]);
                }
                //
            }


            // So not get total 

            $baseAmount = $invoice->invoiceItems()->sum('taxable_amount');
            $subtotalAmount = $baseAmount  + $invoice->invoiceCharges()->sum('taxable_amount');
            $taxAmount = $invoice->invoiceItems()->sum('tax_amount') + $invoice->invoiceCharges()->sum('tax_amount');
            $totalAmount = $subtotalAmount + $taxAmount;


            $invoice->update([
                'base_amount' => $baseAmount,
                'subtotal' => $subtotalAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
            ]);

            $invoice->refresh();

            //
        });


        //
    }

    public function generateProductListingInvoiceData(ProductListing $productListing, $isEnforce = false)
    {
        Log::info("Generating invoice for Product Listing ID: {$productListing->id}");

        try {

            DB::transaction(function () use ($productListing) {

                $productListing->load([
                    'seller',
                    'productListingInvoices',
                    'listingItems.product',
                    'listingItems.listingPackages.shipmentPackages',
                    'listingItems.orderItems',
                ]);

                if ($productListing->productListingInvoices->isNotEmpty()) {
                    throw new RuntimeException(
                        "Invoice already exists for Listing Code: {$productListing->listing_code}"
                    );
                }

                $invoice = $productListing->productListingInvoices()->create([
                    'user_id' => $productListing->seller_id,
                    'product_listing_id' => $productListing->id,
                    'reference' => $productListing->listing_code,
                    'invoice_date' => now(),
                    'invoice_type' => InvoiceStatusEnum::PURCHASE->value,
                    'status' => InvoiceStatusEnum::GENERATED->value,
                    'currency' => MstFinanceSetting::getOrCreate()->currency ?? 'INR',
                ]);

                $totalTaxableAmount = 0;
                $totalDeliveryCharge = 0;
                $totalDeliveryTaxable = 0;
                $totalDeliveryTax = 0;
                $totalShipQty = 0;
                $pkgArr = [];

                foreach ($productListing->listingItems as $item) {

                    // Get only order-related package IDs
                    $orderPackageIds = $item->orderItems
                        ->pluck('product_listing_package_id')
                        ->filter()
                        ->unique()
                        ->toArray();

                    if (empty($orderPackageIds)) {
                        continue;
                    }

                    // Only order related packages
                    $packages = $item->listingPackages
                        ->whereIn('id', $orderPackageIds);

                    foreach ($packages as $package) {

                        if ($package->sold_qty <= 0) {
                            continue;
                        }

                        $shipQty = 0;

                        foreach ($package->shipmentPackages as $shipment) {

                            // Ignore market orders
                            if (!$shipment->order_id) {
                                continue;
                            }

                            // Delivery charge for order shipments
                            $deliveryData = $this->getDeliveryCharge(
                                $productListing->seller->charge_level_code,
                                $shipment
                            );

                            $totalDeliveryTaxable += $deliveryData->charge_taxable;
                            $totalDeliveryTax += $deliveryData->charge_tax;
                            $totalDeliveryCharge +=   $totalDeliveryTaxable  + $totalDeliveryTax;


                            $pkgArr[] = [
                                'order_qty' => $shipment->qty,
                                'pack_size' => $shipment->pack_size,
                                'pack_price' => $shipment->pack_price,
                                'pack_unit' => $shipment->pack_unit,
                                'pack_type_unit' => $shipment->pack_type_unit,
                            ];

                            if ($shipment->seller_status != ShipmentStatusEnum::NOT_PICKED_UP->value) {
                                $shipQty += $shipment->qty;
                                $totalShipQty += $shipment->qty;
                            }
                        }

                        $package->update([
                            'ship_qty' => $shipQty,
                        ]);

                        if ($shipQty <= 0) {
                            continue;
                        }

                        $product = $item->product;

                        if (!$product) {
                            Log::error("Missing product relation for ListingItem ID: {$item->id}");
                            continue;
                        }

                        $itemTaxable = $shipQty * $package->pack_price;
                        $totalTaxableAmount += $itemTaxable;

                        $invoice->invoiceItems()->create([
                            'product_code' => $product->product_code ?? 'N/A',
                            'product_name' => $product->name ?? 'Unknown Product',
                            'order_qty' => $package->sold_qty,
                            'ship_qty' => $shipQty,
                            'pack_size' => $package->pack_size,
                            'pack_unit' => $package->pack_unit,
                            'pack_type_unit' => $package->pack_type_unit,
                            'pack_price' => $package->pack_price,
                            'taxable_amount' => $itemTaxable,
                            'tax_amount' => 0,
                            'total_amount' => $itemTaxable,
                        ]);
                    }
                }

                // Delivery Charge (only if applicable)
                if (!$productListing->is_seller_dropoff && $totalDeliveryTaxable > 0) {

                    $invoice->invoiceCharges()->create([
                        'charge_name' => 'Delivery Charge',
                        'qty' => 1,
                        'ship_qty' => 1,
                        'taxable_amount' => -$totalDeliveryTaxable,
                        'tax_amount' => -$totalDeliveryTax,
                        'total_amount' => -$totalDeliveryCharge,
                    ]);
                }

                // Platform Fee
                $chargeService = app(ChargeCalculationService::class);

                $platformCharge = $chargeService->calculatePlatformFee(
                    $productListing->seller->charge_level_code,
                    $totalTaxableAmount,
                    $pkgArr
                );

                $invoice->invoiceCharges()->create([
                    'charge_name' => $platformCharge['charge_name'] ?? 'Platform Fee',
                    'qty' => 1,
                    'ship_qty' => 1,
                    'taxable_amount' => -$platformCharge['taxable_amount'],
                    'tax_amount' => -$platformCharge['tax_amount'],
                    'total_amount' => -$platformCharge['total_amount'],
                ]);

                // Final Totals
                $baseAmount = $invoice->invoiceItems()->sum('taxable_amount');
                $chargeTaxable = $invoice->invoiceCharges()->sum('taxable_amount');
                $taxAmount = $invoice->invoiceItems()->sum('tax_amount')
                    + $invoice->invoiceCharges()->sum('tax_amount');

                $subtotal = $baseAmount + $chargeTaxable;
                $totalAmount = $subtotal + $taxAmount;

                $invoice->update([
                    'base_amount' => $baseAmount,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                ]);
            });
        } catch (Throwable $e) {

            Log::error('Product Listing Invoice Failed', [
                'listing_id' => $productListing->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }


    private function getDeliveryCharge($chargeLevelCode, ShipmentPackage $shipmentPackage)
    {

        $chargeService = app(ChargeCalculationService::class);

        // Create Arr 
        $pkg =  [
            [
                'order_qty'  => $shipmentPackage->qty,
                'pack_size'  => $shipmentPackage->pack_size,
                'pack_price' => $shipmentPackage->pack_price,
                'pack_unit'  => $shipmentPackage->pack_unit,
                'pack_type_unit' => $shipmentPackage->pack_type_unit,
            ]
        ];

        $deliveryChargesData  = $chargeService->calculateDeliveryCharges(
            $chargeLevelCode,
            $pkg,
            $shipmentPackage->is_buyer_pickup ?? false,
            $shipmentPackage->is_seller_dropoff ?? false
        );

        return (object) $deliveryChargesData;
    }



    //
}
