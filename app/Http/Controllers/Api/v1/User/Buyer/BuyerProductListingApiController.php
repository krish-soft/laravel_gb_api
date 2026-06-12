<?php

namespace App\Http\Controllers\Api\v1\User\Buyer;

use App\Http\Controllers\ApiResponseWithAuthController;
use App\Models\Buyer\Order\DemandOrderItem;
use App\Models\Buyer\Order\OrderItem;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Master\Product\MstProduct;
use App\Models\Seller\Product\ProductListing;
use App\Policies\Buyer\BuyerPolicyManager;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class BuyerProductListingApiController extends ApiResponseWithAuthController
{
    //

    // What buyer can see in product listing

    /**
     * Product summary for buyer listing screen.
     *
     * Sequence rule:
     * 1) Previously purchased products first.
     * 2) Remaining products next.
     * Existing filters and payload shape remain unchanged.
     */
    public function getBuyerProductSummary(Request $request)
    {
        $buyer = $request->user();
        $pastPurchasedProductIds = $this->getPastPurchasedProductIds((int)$buyer->id);
        $pastPurchasedLookup = array_flip($pastPurchasedProductIds);

        $limit = min((int) $request->get('limit', 20), 100);
        $offset = (int) $request->get('offset', 0);
        $sortBy = $request->get('sort_by', 'product_name');
        $sortDir = $request->get('sort_dir', 'asc') === 'desc';

        $productListings = $this->baseBuyerListingQuery($buyer);

        $products = $productListings
            ->flatMap->listingItems
            ->groupBy('product.id')
            ->map(function ($items) {

                $packages = $items->flatMap->listingPackages;

                $totalQty = $packages->sum('qty');
                $totalSold = $packages->sum('sold_qty');
                $available = $totalQty - $totalSold;

                // Unit Wise total
                $totalWeight = $packages->sum(fn ($pkg) => $pkg->pack_size * $pkg->qty);
                $soldWeight = $packages->sum(fn ($pkg) => $pkg->pack_size * $pkg->sold_qty);

                if ($available <= 0) {
                    return null;
                }

                return [
                    'product' => $items->first()->product,
                    'past_purchased' => isset($pastPurchasedLookup[$items->first()->product_id]),
                    'total_qty' => $totalQty,
                    'total_sold_qty' => $totalSold,
                    'total_available_qty' => $available,
                    'total_weight' => $totalWeight,
                    'sold_weight' => $soldWeight,
                    'available_weight' => $totalWeight - $soldWeight,
                    'unit' => optional($packages->first())->pack_unit ?? 'N/A',
                ];
            })
            ->filter()
            ->values();

        // Prioritize past purchased products first
        [$pastPurchasedProducts, $otherProducts] = $products->partition(
            fn($product) => (bool)($product['past_purchased'] ?? false)
        );

        // Keep existing sorting behavior within each segment
        if ($sortBy === 'product_name') {
            $pastPurchasedProducts = $pastPurchasedProducts->sortBy(
                fn($p) => $p['product']->name,
                SORT_NATURAL | SORT_FLAG_CASE,
                $sortDir
            );

            $otherProducts = $otherProducts->sortBy(
                fn($p) => $p['product']->name,
                SORT_NATURAL | SORT_FLAG_CASE,
                $sortDir
            );
        }

        $products = $pastPurchasedProducts->concat($otherProducts)->values();

        $data = $products
            ->slice($offset, $limit)
            ->values();

        return $this->successResponse(__('messages.success_messages.success_get'), $data);
    }

    /**
     * Package list for a selected product.
     *
     * Sequence rule:
     * 1) Packages from previously purchased sellers first.
     * 2) Remaining packages next.
     * Existing filter and sorting behavior is preserved inside each group.
     */
    public function getBuyerProductPackages(Request $request, $productId)
    {
        if (!is_numeric($productId)) {
            return $this->showErrorMessage(__('messages.error_messages.invalid_product'), 422);
        }

        $productId = (int) $productId;

        if (!MstProduct::query()->where('id', $productId)->exists()) {
            return $this->showErrorMessage(__('messages.error_messages.not_found'), 404);
        }

        $buyer = $request->user();
        $pastSellerIds = $this->getPastPurchasedSellerIds((int)$buyer->id);
        $pastSellerLookup = array_flip($pastSellerIds);

        $limit = min((int) $request->get('limit', 20), 100);
        $offset = (int) $request->get('offset', 0);
        $sortBy = $request->get('sort_by');
        $sortDir = $request->get('sort_dir', 'asc') === 'desc';

        $minPrice = $request->get('min_price');
        $maxPrice = $request->get('max_price');
        $minRemainingQty = $request->get('min_remaining_qty');

        $productListings = $this->baseBuyerListingQuery($buyer, $productId);

        $items = $productListings
            ->flatMap->listingItems
            ->where('product_id', $productId);

        $sellerIdByItemId = $items->mapWithKeys(function ($item) {
            return [$item->id => $item->productListing?->seller_id];
        })->all();

        $packages = $items
            ->flatMap->listingPackages
            ->filter(function ($pkg) use ($minPrice, $maxPrice, $minRemainingQty) {

                $available = $pkg->qty - $pkg->sold_qty;

                if ($available <= 0) {
                    return false;
                }
                if ($minRemainingQty !== null && $available < $minRemainingQty) {
                    return false;
                }
                if ($minPrice !== null && $pkg->pack_price < $minPrice) {
                    return false;
                }
                if ($maxPrice !== null && $pkg->pack_price > $maxPrice) {
                    return false;
                }

                return true;
            });

        // Prioritize packages from sellers buyer has purchased from in the past
        [$pastSellerPackages, $otherPackages] = $packages->partition(function ($pkg) use ($sellerIdByItemId, $pastSellerLookup) {
            $sellerId = $sellerIdByItemId[$pkg->product_listing_item_id] ?? null;
            return $sellerId && isset($pastSellerLookup[$sellerId]);
        });

        // Keep existing sorting behavior within each segment
        $pastSellerPackages = $this->sortPackageCollection($pastSellerPackages, $sortBy, $sortDir);
        $otherPackages = $this->sortPackageCollection($otherPackages, $sortBy, $sortDir);

        $packages = $pastSellerPackages->concat($otherPackages)->values();

        $packages = $packages
            ->map(function ($pkg) {
                return [
                    'package_id' => $pkg->id,
                    'product_listing_item_id' => $pkg->product_listing_item_id,
                    'quality_grade' => $pkg->quality_grade,
                    'pack_size' => $pkg->pack_size,
                    'pack_unit' => $pkg->pack_unit,
                    'pack_type_unit' => $pkg->pack_type_unit,
                    'price' => $pkg->pack_price,
                    'total_qty' => $pkg->qty,
                    'sold_qty' => $pkg->sold_qty,

                    'available_qty' => $pkg->qty - $pkg->sold_qty,
                    'total_weight' => $pkg->pack_size * $pkg->qty,
                    //
                    'sold_weight' => $pkg->pack_size * $pkg->sold_qty,
                    'remaining_weight' => $pkg->pack_size * ($pkg->qty - $pkg->sold_qty),

                    // pictures can be added here if needed
                    'picture_url' => $pkg->picture_url,
                    'picture2_url' => $pkg->picture2_url,
                    'picture3_url' => $pkg->picture3_url,

                    //
                    'nickname' => $pkg->seller->nickname ?? 'N/A',
                ];
            })
            ->slice($offset, $limit)
            ->values();

        $product = optional($items->first())->product;

        $data = [
            'product' => $product,
            'packages' => $packages,
        ];

        return $this->successResponse(__('messages.success_messages.success_get'), $data);
    }

    /**
     * Base listing query visible to current buyer after policy checks.
     */
    private function baseBuyerListingQuery($buyer, ?int $productId = null)
    {
        $query = ProductListing::with([
            'listingItems.product',
            'listingItems.productListing',
            'listingItems.listingPackages',
        ])
            ->where('is_active', true)
            ->where('is_expired', false)
            ->where('is_sold', false);

        if ($productId) {
            $query->whereHas('listingItems', function ($q) use ($productId) {
                $q->where('product_id', $productId);
            });
        }

        return $query->get()
            ->filter(
                fn ($listing) => BuyerPolicyManager::canBuyerSeeProductListing($buyer, $listing)
            );
    }

    /**
     * Applies existing package sorting keys to a package collection.
     */
    private function sortPackageCollection(Collection $packages, ?string $sortBy, bool $sortDir): Collection
    {
        return match ($sortBy) {
            'price' => $packages->sortBy('pack_price', SORT_REGULAR, $sortDir),
            'remaining_qty' => $packages->sortBy(fn($p) => $p->qty - $p->sold_qty, SORT_REGULAR, $sortDir),
            'pack_size' => $packages->sortBy('pack_size', SORT_REGULAR, $sortDir),
            default => $packages,
        };
    }

    /**
     * Product IDs purchased by buyer from regular and demand orders.
     */
    private function getPastPurchasedProductIds(int $buyerId): array
    {
        $orderProductIds = OrderItem::query()
            ->whereHas('order', fn($q) => $q->where('buyer_id', $buyerId))
            ->pluck('product_id');

        $demandOrderProductIds = DemandOrderItem::query()
            ->whereHas('demandOrder', fn($q) => $q->where('buyer_id', $buyerId))
            ->pluck('product_id');

        return $orderProductIds
            ->merge($demandOrderProductIds)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Seller IDs purchased by buyer, derived from order and shipment history.
     */
    private function getPastPurchasedSellerIds(int $buyerId): array
    {
        $orderSellerIds = OrderItem::query()
            ->whereHas('order', fn($q) => $q->where('buyer_id', $buyerId))
            ->pluck('seller_id');

        $shipmentSellerIds = ShipmentPackage::query()
            ->where('buyer_id', $buyerId)
            ->whereNotNull('seller_id')
            ->pluck('seller_id');

        return $orderSellerIds
            ->merge($shipmentSellerIds)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    // # ORG

    // public function getBuyerProductListing(Request $request)
    // {
    //     $buyer = $request->user();

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Pagination (mobile-safe)
    //     |--------------------------------------------------------------------------
    //     | Frontend MUST reset offset = 0 when sort/filter changes
    //     */
    //     $loadAll = filter_var($request->get('load_all', false), FILTER_VALIDATE_BOOLEAN);
    //     $limit   = min((int) $request->get('limit', 100), 100);
    //     $offset  = (int) $request->get('offset', 0);

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Sorting
    //     |--------------------------------------------------------------------------
    //     | Allowed values:
    //     | product_name | price | remaining_qty | pack_size | pack_type_unit
    //     */
    //     $sortBy  = $request->get('sort_by');
    //     $sortDir = $request->get('sort_dir', 'asc') === 'desc';

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Filters (all optional)
    //     |--------------------------------------------------------------------------
    //     */
    //     $minRemainingQty = $request->get('min_remaining_qty');
    //     $minPrice        = $request->get('min_price');
    //     $maxPrice        = $request->get('max_price');

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Load listings
    //     |--------------------------------------------------------------------------
    //     */
    //     $productListings = ProductListing::with([
    //         'listingItems.product',
    //         'listingItems.listingPackages'
    //     ])
    //         ->where('is_active', true)
    //         ->where('is_expired', false)
    //         ->where('is_sold', false)
    //         ->get()
    //         ->filter(
    //             fn($listing) =>
    //             BuyerPolicyManager::canBuyerSeeProductListing($buyer, $listing)
    //         );

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Group by PRODUCT (main parent)
    //     |--------------------------------------------------------------------------
    //     */
    //     $products = $productListings
    //         ->flatMap->listingItems
    //         ->groupBy('product.id')
    //         ->map(function ($itemsByProduct) use (
    //             $sortBy,
    //             $sortDir,
    //             $minRemainingQty,
    //             $minPrice,
    //             $maxPrice
    //         ) {

    //             $packages = $itemsByProduct
    //                 ->flatMap->listingPackages
    //                 ->filter(function ($pkg) use ($minRemainingQty, $minPrice, $maxPrice) {

    //                     $remainingQty = $pkg->qty - $pkg->sold_qty;

    //                     if ($remainingQty <= 0) return false;

    //                     if ($minRemainingQty !== null && $remainingQty < $minRemainingQty) {
    //                         return false;
    //                     }

    //                     if ($minPrice !== null && $pkg->pack_price < $minPrice) {
    //                         return false;
    //                     }

    //                     if ($maxPrice !== null && $pkg->pack_price > $maxPrice) {
    //                         return false;
    //                     }

    //                     return true;
    //                 });

    //             /*
    //             |--------------------------------------------------------------------------
    //             | Package-level sorting
    //             |--------------------------------------------------------------------------
    //             */
    //             $packages = match ($sortBy) {
    //                 'price' =>
    //                 $packages->sortBy(fn($p) => $p->pack_price, SORT_REGULAR, $sortDir),

    //                 'remaining_qty' =>
    //                 $packages->sortBy(fn($p) => ($p->qty - $p->sold_qty), SORT_REGULAR, $sortDir),

    //                 'pack_size' =>
    //                 $packages->sortBy(fn($p) => $p->pack_size, SORT_REGULAR, $sortDir),

    //                 'pack_type_unit' =>
    //                 $packages->sortBy(fn($p) => $p->pack_type_unit, SORT_REGULAR, $sortDir),

    //                 default => $packages
    //             };

    //             $totalQty = $packages->sum('qty');
    //             $totalSoldQty = $packages->sum('sold_qty');
    //             $totalAvailableQty = $totalQty - $totalSoldQty;

    //             if ($totalAvailableQty <= 0) {
    //                 return null;
    //             }

    //             return [
    //                 'product' => $itemsByProduct->first()->product,
    //                 'listing_packages' => $packages->values(),
    //                 'total_qty' => $totalQty,
    //                 'total_sold_qty' => $totalSoldQty,
    //                 'total_available_qty' => $totalAvailableQty,
    //             ];
    //         })
    //         ->filter()
    //         ->values();

    //     // Log::info(json_encode($products, JSON_PRETTY_PRINT));
    //     /*
    //     |--------------------------------------------------------------------------
    //     | Product-name sorting (product-level)
    //     |--------------------------------------------------------------------------
    //     */
    //     if ($sortBy === 'product_name') {
    //         $products = $products->sortBy(
    //             fn($p) => $p['product']->name,
    //             SORT_NATURAL | SORT_FLAG_CASE,
    //             $sortDir
    //         )->values();
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Pagination / Load all
    //     |--------------------------------------------------------------------------
    //     */
    //     if ($loadAll) {
    //         return $products->values();
    //     }

    //     return $products
    //         ->slice($offset, $limit)
    //         ->values();
    // }

    //
}
