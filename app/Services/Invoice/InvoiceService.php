<?php

namespace App\Services\Invoice;

use App\Enum\Common\Invoice\InvoiceStatusEnum;
use App\Enum\Common\Invoice\InvoiceTypeEnum;
use App\Enum\Common\Order\OrderFlagsEum;
use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Enum\Common\Shipment\ShipmentTypeEnum;
use App\Models\Buyer\Order\DemandOrder;
use App\Models\Buyer\Order\Order;
use App\Models\Common\Accounting\Account;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Master\Setting\MstBusinessSetting;
use App\Models\Master\Setting\MstFinanceSetting;
use App\Models\Seller\Product\ProductListing;
use App\Services\Common\Charge\ChargeCalculationService;
use App\Services\Common\Price\ProductPriceCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class InvoiceService
{

    // Direct Order Invoice
    public function generateOrderInvoiceData(Order $order)
    {
        // Not PDF only Invoice data

        //    
        try {
            DB::transaction(function () use ($order) {

                $order->load([
                    'buyer',
                    'invoices',
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
                $invoice = $order->invoices()->create([
                    'user_id' => $order->buyer_id,
                    'order_id' => $order->id,

                    'reference' => $order->order_number, // we can also have separate invoice reference if needed

                    'invoice_date' => now(),
                    'invoice_type' => InvoiceTypeEnum::SALES->value, // or 'proforma' based on your logic

                    'status' => InvoiceStatusEnum::GENERATED->value, // or 'draft' based on your logic
                    'payment_status' => $order->payment_status,

                    'currency' => $order->currency,

                    'platform_bill_addr_code' => $business->bill_addr_code ?? $business->addr_code, // fix of platform

                    'customer_bill_addr_code' => $order->bill_addr_code, // Optional
                    'customer_ship_addr_code' => $order->ship_addr_code, // need
                ]);


                // First Create a Invoice for each Order Item then base on shipment package delivered or not make reverse or final invoice
                foreach ($orderItems as $orderItem) {

                    // 
                    $taxableAmount = $orderItem->ship_qty * $orderItem->pack_price; // we are storing in each accounts  , we can also have separate field for base amount without tax and charges if needed
                    $taxAmount = 0; // we can also calculate tax based on tax code if needed
                    $totalAmount = $taxableAmount + $taxAmount;

                    // Base on order create all 
                    $invoice->invoiceItems()->create([
                        'item_code' => $orderItem->product_code,
                        'item_name' => $orderItem->product_name . "[$orderItem->pack_size $orderItem->pack_unit ($orderItem->pack_type_unit)]",

                        'order_qty' => $orderItem->order_qty,
                        'unit_price' => $orderItem->pack_price,

                        'ship_qty' => $orderItem->ship_qty,
                        'ship_unit_price' => $orderItem->pack_price,

                        'taxable_amount' => $taxableAmount,
                        'tax_amount' => $taxAmount,
                        'total_amount' => $totalAmount,
                    ]);

                    //
                }

                // TODO:: Infuture if we change to calculate automatically then will see
                // We already have charges so no need to calculate
                // Charges same way
                // We can not return or take more
                foreach ($orderCharges as $orderCharge) {
                    $invoice->invoiceCharges()->create([
                        'charge_name' => $orderCharge->charge_name,
                        'qty' => 1,
                        'taxable_amount' => $orderCharge->taxable_amount,
                        'tax_amount' => $orderCharge->tax_amount,
                        'total_amount' => $orderCharge->total_amount,
                    ]);

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

                // Mark Order Invoice
                $order->order_status = OrderStatusEnum::INVOICED->value;
                $order->removeFlag(OrderFlagsEum::INVOICING_ERROR); // remove flag if exists
                $order->save();
                //
            });
        } catch (Throwable $e) {

            $order->addFlag(OrderFlagsEum::INVOICING_ERROR, $e->getMessage());

            throw $e;
        }



        //
    }


    // Demand Order Invoice
    public function generateDemandOrderInvoiceData(DemandOrder $order)
    {
        // Not PDF only Invoice data

        //     
        try {
            DB::transaction(function () use ($order) {

                $order->load([
                    'buyer',
                    'invoices',
                    'shipmentPackages',
                    'demandOrderCharges',
                    'demandOrderItems', // seller not to load 
                ]);

                $buyer = $order->buyer;
                $orderItems = $order->demandOrderItems;
                $orderCharges = $order->demandOrderCharges;
                $shipmentPackages = $order->shipmentPackages;

                // if not found throw runtime exception and it will be handled by job and can be retried later, we can also log the error for debugging
                if ($shipmentPackages->isEmpty() || $orderItems->isEmpty()) {
                    throw new RuntimeException("No shipment packages or order items found for Order ID: {$order->id}");
                }

                $business = MstBusinessSetting::getOrCreate(); // Assuming you have a business settings table with necessary info

                // Invoice 
                $invoice = $order->invoices()->create([
                    'user_id' => $order->buyer_id,
                    'order_id' => $order->id,

                    'reference' => $order->order_number, // we can also have separate invoice reference if needed

                    'invoice_date' => now(),
                    'invoice_type' => InvoiceTypeEnum::SALES->value, // or 'proforma' based on your logic

                    'status' => InvoiceStatusEnum::GENERATED->value, // or 'draft' based on your logic
                    'payment_status' => $order->payment_status,

                    'currency' => $order->currency,

                    'platform_bill_addr_code' => $business->bill_addr_code ?? $business->addr_code, // fix of platform

                    'customer_bill_addr_code' => $order->bill_addr_code, // Optional
                    'customer_ship_addr_code' => $order->ship_addr_code, // need
                ]);


                // First Create a Invoice for each Order Item then base on shipment package delivered or not make reverse or final invoice
                foreach ($orderItems as $orderItem) {

                    // Price We have to calculate from Price Module 
                    // Because its demand order we have to calculate baseon what market price set in morning.

                    $productId = $orderItem->product_id;
                    // $marketId = $order->market_id;

                    $pricedata = app(ProductPriceCalculationService::class)->calculateFinalPrice(
                        $productId,
                        $buyer->charge_level_code,
                        $buyer->user_type,
                        $orderItem->pack_size,
                        $orderItem->pack_unit,
                        $invoice->invoice_date // we have to take price based on invoice date because order can be place before but invoice generate later and price can be different based on date
                    );


                    if (!$pricedata || $pricedata->final_price <= 0) {
                        throw new RuntimeException("Price data not found for product Code: {$orderItem->product_code} in Order Number: {$order->order_number}");
                    }

                    $totalShipQty = $orderItem->ship_qty + $orderItem->seller_ship_qty; // we can also have separate field for ship qty and seller ship qty if needed, because in some case we can have different ship qty from seller and what we record in order item, so to avoid confusion we can have separate field for that

                    // 
                    $taxableAmount =  $totalShipQty * $pricedata->final_price; // we are storing in each accounts  , we can also have separate field for base amount without tax and charges if needed
                    $taxAmount = 0; // we can also calculate tax based on tax code if needed
                    $totalAmount = $taxableAmount + $taxAmount;

                    // Base on order create all 
                    $invoice->invoiceItems()->create([
                        'item_code' => $orderItem->product_code,
                        'item_name' => $orderItem->product_name . "[$orderItem->pack_size $orderItem->pack_unit ($orderItem->pack_type_unit)]",

                        'order_qty' => $orderItem->order_qty,
                        'unit_price' => $orderItem->pack_price, // Alwasy waht order place on price

                        'ship_qty' => $totalShipQty,
                        'ship_unit_price' => $pricedata->final_price, // Latest optimize

                        'taxable_amount' => $taxableAmount,
                        'tax_amount' => $taxAmount,
                        'total_amount' => $totalAmount,
                    ]);

                    //
                }

                // TODO:: Infuture if we change to calculate automatically then will see
                // We already have charges so no need to calculate
                // Charges same way
                // We can not return or take more
                foreach ($orderCharges as $orderCharge) {
                    $invoice->invoiceCharges()->create([
                        'charge_name' => $orderCharge->charge_name,
                        'qty' => 1,
                        'taxable_amount' => $orderCharge->taxable_amount,
                        'tax_amount' => $orderCharge->tax_amount,
                        'total_amount' => $orderCharge->total_amount,
                    ]);

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

                // Mark Order Invoice
                $order->order_status = OrderStatusEnum::INVOICED->value;
                $order->removeFlag(OrderFlagsEum::INVOICING_ERROR); // remove flag if exists
                $order->save();
                //
            });
        } catch (Throwable $e) {

            $order->addFlag(OrderFlagsEum::INVOICING_ERROR, $e->getMessage());

            throw $e;
        }


        //
    }

    // Product Listing Invoice
    public function generateProductListingInvoiceData(ProductListing $productListing, $isEnforce = false)
    {
        // Log::info("Generating invoice for Product Listing ID: {$productListing->id}");

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


                    // Only order related packages
                    $packages = $item->listingPackages;

                    foreach ($packages as $package) {

                        if ($package->sold_qty <= 0 && $package->demand_sold_qty <= 0) {
                            continue;
                        }

                        $shipQty = 0;

                        foreach ($package->shipmentPackages as $shpPkg) {


                            // We only need to consider shipment type is PICKUP & STATUS COMPLETED

                            if (
                                $shpPkg->shipment->shipment_type !== ShipmentTypeEnum::PICKUP->value
                                && $shpPkg->shipment->status !== ShipmentStatusEnum::COMPLETED->value
                                && in_array($shpPkg->status, [ShipmentStatusEnum::PENDING->value, ShipmentStatusEnum::NOT_PICKED_UP->value])
                            ) {
                                continue;
                            }


                            // Pack Price We need to decide 
                            // For Direct Order we can record what farmer keep so will go as it is 
                            // But for demand order what is price from Pricing module based morning data feed

                            // First need to find Order or DemandOrder Shipment Package from Pacakge Numbers not from ids 

                            // TODO: PENDING


                            // Delivery charge for order shipments
                            $deliveryData = $this->getDeliveryCharge(
                                $productListing->seller->charge_level_code,
                                $shpPkg
                            );

                            $totalDeliveryTaxable += $deliveryData->charge_taxable;
                            $totalDeliveryTax += $deliveryData->charge_tax;
                            $totalDeliveryCharge +=   $totalDeliveryTaxable  + $totalDeliveryTax;


                            $pkgArr[] = [
                                'order_qty' => $shpPkg->qty,
                                'pack_size' => $shpPkg->pack_size,
                                'pack_price' => $shpPkg->pack_price,
                                'pack_unit' => $shpPkg->pack_unit,
                                'pack_type_unit' => $shpPkg->pack_type_unit,
                            ];

                            if ($shpPkg->seller_status != ShipmentStatusEnum::NOT_PICKED_UP->value) {
                                $shipQty += $shpPkg->qty;
                                $totalShipQty += $shpPkg->qty;
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
                            'item_code' => $product->product_code ?? 'N/A',
                            'item_name' => $product->name . " [Package: {$package->package_number}]",
                            'order_qty' =>  $package->sold_qty,
                            'ship_qty' => $shipQty,

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
                        'taxable_amount' => $totalDeliveryTaxable,
                        'tax_amount' => $totalDeliveryTax,
                        'total_amount' => $totalDeliveryCharge,
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
                    'taxable_amount' => $platformCharge['taxable_amount'],
                    'tax_amount' => $platformCharge['tax_amount'],
                    'total_amount' => $platformCharge['total_amount'],
                ]);

                // Final Totals
                $baseAmount = $invoice->invoiceItems()->sum('taxable_amount');
                $chargeTaxable = $invoice->invoiceCharges()->sum('taxable_amount');
                $taxAmount = $invoice->invoiceItems()->sum('tax_amount') + $invoice->invoiceCharges()->sum('tax_amount');

                // Its product Listing so we have to take our charge from seller
                $subtotal = $baseAmount - $chargeTaxable;
                $totalAmount = $subtotal - $taxAmount;

                $invoice->update([
                    'base_amount' => $baseAmount,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                ]);



                // Mark Product Listing Invoice
                $productListing->status = OrderStatusEnum::INVOICED->value;
                $productListing->save();
            });

            //
        }  catch (Throwable $e) {

            $productListing->addFlag(OrderFlagsEum::INVOICING_ERROR, $e->getMessage());

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
