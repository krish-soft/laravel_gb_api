<?php

namespace App\Http\Controllers\Api\v1\User\Buyer;

use App\Http\Controllers\ApiResponseWithAuthController;
use App\Http\Controllers\Controller;
use App\Models\Seller\Product\ProductListing;
use App\Policies\Buyer\BuyerPolicyManager;
use Illuminate\Http\Request;

class BuyerProductListingApiController extends ApiResponseWithAuthController
{
    //

    // What buyer can see in product listing

    public function getBuyerProductListing(Request $request)
    {
        $buyer = $request->user();

        /*
    |--------------------------------------------------------------------------
    | Pagination (mobile-safe)
    |--------------------------------------------------------------------------
    | Frontend MUST reset offset = 0 when sort/filter changes
    */
        $loadAll = filter_var($request->get('load_all', false), FILTER_VALIDATE_BOOLEAN);
        $limit   = min((int) $request->get('limit', 100), 100);
        $offset  = (int) $request->get('offset', 0);

        /*
    |--------------------------------------------------------------------------
    | Sorting
    |--------------------------------------------------------------------------
    | Allowed values:
    | product_name | price | remaining_qty | pack_size | pack_type_unit
    */
        $sortBy  = $request->get('sort_by');
        $sortDir = $request->get('sort_dir', 'asc') === 'desc';

        /*
    |--------------------------------------------------------------------------
    | Filters (all optional)
    |--------------------------------------------------------------------------
    */
        $minRemainingQty = $request->get('min_remaining_qty');
        $minPrice        = $request->get('min_price');
        $maxPrice        = $request->get('max_price');

        /*
    |--------------------------------------------------------------------------
    | Load listings
    |--------------------------------------------------------------------------
    */
        $productListings = ProductListing::with([
            'listingItems.product',
            'listingItems.listingPackages'
        ])
            ->where('is_active', true)
            ->where('is_expired', false)
            ->where('is_sold', false)
            ->get()
            ->filter(
                fn($listing) =>
                BuyerPolicyManager::canBuyerSeeProductListing($buyer, $listing)
            );

        /*
    |--------------------------------------------------------------------------
    | Group by PRODUCT (main parent)
    |--------------------------------------------------------------------------
    */
        $products = $productListings
            ->flatMap->listingItems
            ->groupBy('product.id')
            ->map(function ($itemsByProduct) use (
                $sortBy,
                $sortDir,
                $minRemainingQty,
                $minPrice,
                $maxPrice
            ) {

                $packages = $itemsByProduct
                    ->flatMap->listingPackages
                    ->filter(function ($pkg) use ($minRemainingQty, $minPrice, $maxPrice) {

                        $remainingQty = $pkg->qty - $pkg->sold_qty;

                        if ($remainingQty <= 0) return false;

                        if ($minRemainingQty !== null && $remainingQty < $minRemainingQty) {
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

                /*
            |--------------------------------------------------------------------------
            | Package-level sorting
            |--------------------------------------------------------------------------
            */
                $packages = match ($sortBy) {
                    'price' =>
                    $packages->sortBy(fn($p) => $p->pack_price, SORT_REGULAR, $sortDir),

                    'remaining_qty' =>
                    $packages->sortBy(fn($p) => ($p->qty - $p->sold_qty), SORT_REGULAR, $sortDir),

                    'pack_size' =>
                    $packages->sortBy(fn($p) => $p->pack_size, SORT_REGULAR, $sortDir),

                    'pack_type_unit' =>
                    $packages->sortBy(fn($p) => $p->pack_type_unit, SORT_REGULAR, $sortDir),

                    default => $packages
                };

                $totalQty = $packages->sum('qty');
                $totalSoldQty = $packages->sum('sold_qty');
                $totalAvailableQty = $totalQty - $totalSoldQty;

                if ($totalAvailableQty <= 0) {
                    return null;
                }

                return [
                    'product' => $itemsByProduct->first()->product,
                    'total_qty' => $totalQty,
                    'total_sold_qty' => $totalSoldQty,
                    'total_available_qty' => $totalAvailableQty,
                ];
            })
            ->filter()
            ->values();

        /*
    |--------------------------------------------------------------------------
    | Product-name sorting (product-level)
    |--------------------------------------------------------------------------
    */
        if ($sortBy === 'product_name') {
            $products = $products->sortBy(
                fn($p) => $p['product']->name,
                SORT_NATURAL | SORT_FLAG_CASE,
                $sortDir
            )->values();
        }

        /*
    |--------------------------------------------------------------------------
    | Pagination / Load all
    |--------------------------------------------------------------------------
    */
        if ($loadAll) {
            return $products->values();
        }

        return $products
            ->slice($offset, $limit)
            ->values();
    }




    // public function getBuyerProductListing(Request $request)
    // {
    //     $buyer = $request->user();

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

    //     return $productListings
    //         ->flatMap->listingItems
    //         ->groupBy('product.id')
    //         ->map(function ($itemsByProduct) {

    //             $packages = $itemsByProduct->flatMap->listingPackages;

    //             $totalQty = $packages->sum('qty');
    //             $totalSoldQty = $packages->sum('sold_qty');
    //             $totalAvailableQty = $totalQty - $totalSoldQty;

    //             return [
    //                 'product' => $itemsByProduct->first()->product,
    //                 'total_qty' => $totalQty,
    //                 'total_sold_qty' => $totalSoldQty,
    //                 'total_available_qty' => $totalAvailableQty,
    //             ];
    //         })
    //         ->values();
    // }













    //
}
