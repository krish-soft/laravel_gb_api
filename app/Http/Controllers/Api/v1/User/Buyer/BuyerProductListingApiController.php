<?php

namespace App\Http\Controllers\Api\v1\User\Buyer;

use App\Http\Controllers\ApiResponseWithAuthController;
use App\Http\Controllers\Controller;
use App\Models\Seller\Product\ProductListing;
use App\Policies\Buyer\BuyerPolicyManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BuyerProductListingApiController extends ApiResponseWithAuthController
{
    //

    // What buyer can see in product listing


    public function getBuyerProductSummary(Request $request)
    {
        $buyer = $request->user();

        $limit  = min((int)$request->get('limit', 20), 100);
        $offset = (int)$request->get('offset', 0);
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

                if ($available <= 0) return null;

                return [
                    'product' => $items->first()->product,
                    'total_qty' => $totalQty,
                    'total_sold_qty' => $totalSold,
                    'total_available_qty' => $available,
                ];
            })
            ->filter()
            ->values();

        // Sorting
        if ($sortBy === 'product_name') {
            $products = $products->sortBy('product_name', SORT_NATURAL | SORT_FLAG_CASE, $sortDir);
        }

        $data = $products
            ->slice($offset, $limit)
            ->values();

        return  $this->successResponse(__('messages.success_messages.success_get'), $data);
    }

    public function getBuyerProductPackages(Request $request, $productId)
    {
        $buyer = $request->user();

        $limit  = min((int)$request->get('limit', 20), 100);
        $offset = (int)$request->get('offset', 0);
        $sortBy = $request->get('sort_by');
        $sortDir = $request->get('sort_dir', 'asc') === 'desc';

        $minPrice = $request->get('min_price');
        $maxPrice = $request->get('max_price');
        $minRemainingQty = $request->get('min_remaining_qty');

        $productListings = $this->baseBuyerListingQuery($buyer, $productId);

        $items = $productListings
            ->flatMap->listingItems
            ->where('product_id', $productId);

        $packages = $items
            ->flatMap->listingPackages
            ->filter(function ($pkg) use ($minPrice, $maxPrice, $minRemainingQty) {

                $available = $pkg->qty - $pkg->sold_qty;

                if ($available <= 0) return false;
                if ($minRemainingQty !== null && $available < $minRemainingQty) return false;
                if ($minPrice !== null && $pkg->pack_price < $minPrice) return false;
                if ($maxPrice !== null && $pkg->pack_price > $maxPrice) return false;

                return true;
            });

        // Sorting
        $packages = match ($sortBy) {
            'price' => $packages->sortBy('pack_price', SORT_REGULAR, $sortDir),
            'remaining_qty' => $packages->sortBy(fn($p) => $p->qty - $p->sold_qty, SORT_REGULAR, $sortDir),
            'pack_size' => $packages->sortBy('pack_size', SORT_REGULAR, $sortDir),
            default => $packages
        };

        $packages = $packages
            ->map(function ($pkg) {
                return [
                    'package_id' => $pkg->id,
                    'product_listing_item_id' => $pkg->product_listing_item_id,
                    'pack_size' => $pkg->pack_size,
                    'pack_unit' => $pkg->pack_unit,
                    'pack_type_unit' => $pkg->pack_type_unit,
                    'price' => $pkg->pack_price,
                    'total_qty' => $pkg->qty,
                    'sold_qty' => $pkg->sold_qty,
                    'available_qty' => $pkg->qty - $pkg->sold_qty,
                ];
            })
            ->slice($offset, $limit)
            ->values();

        $product = optional($items->first())->product;

        $data = [
            'product' => $product,
            'packages' => $packages,
        ];

        return  $this->successResponse(__('messages.success_messages.success_get'), $data);
    }



    private function baseBuyerListingQuery($buyer, ?int $productId = null)
    {
        $query = ProductListing::with([
            'listingItems.product',
            'listingItems.listingPackages'
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
                fn($listing) =>
                BuyerPolicyManager::canBuyerSeeProductListing($buyer, $listing)
            );
    }




    ## ORG

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
