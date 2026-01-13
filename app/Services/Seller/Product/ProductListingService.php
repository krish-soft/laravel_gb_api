<?php

namespace App\Services\Seller\Product;

use App\Models\Seller\Product\ProductListing;
use App\Models\Seller\Product\ProductListingPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductListingService
{
    /* =========================================================
       ================= CREATE LISTING ========================
       ========================================================= */

    public function createListing(User $user, array $data): ProductListing
    {
        return DB::transaction(function () use ($user, $data) {

            if (!$user->isSeller()) {
                throw new RuntimeException(
                    __('messages.error_messages.unauthorized_action')
                );
            }

            $listing = new ProductListing();
            $listing->fill($this->onlyFillable($listing, $data));
            $listing->seller_id = $user->id;
            $listing->doc_date = now()->toDateString();
            $listing->is_active = true;
            $listing->is_sold = false;
            $listing->is_partial = false;
            $listing->save();

            foreach ($data['productListingItems'] as $itemData) {

                $item = $listing->listingItems()->create([
                    'product_id' => $itemData['product_id'],
                    'product_variant_id' => $itemData['product_variant_id'] ?? null,
                    'listing_code' => $listing->listing_code,
                ]);

                foreach ($itemData['productListingPackages'] as $pkgData) {

                    if (array_key_exists('sold_qty', $pkgData)) {
                        throw new RuntimeException(
                            __('messages.error_messages.sold_qty_not_editable')
                        );
                    }

                    $item->listingPackages()->create(array_merge(
                        $this->onlyFillable(new ProductListingPackage(), $pkgData),
                        [
                            'listing_code' => $listing->listing_code,
                            'sold_qty' => 0,
                            'is_partial' => false,
                            'is_sold' => false,
                            'is_locked' => false, // transactional only
                        ]
                    ));
                }
            }

            //
            logActivity(
                'product_listing_created',
                $user,
                ProductListing::class,
                $listing->id,
                $listing->listing_code
            );

            return $listing;
        });
    }

    /* =========================================================
       ============ UPDATE LISTING FLAGS ONLY ==================
       ========================================================= */

    public function updateListingFlags(User $user, int $listingId, array $data): ProductListing
    {
        $listing = ProductListing::findOrFail($listingId);
        $this->authorize($user, $listing);

        if ($listing->is_expired) {
            throw new RuntimeException(
                __('messages.error_messages.listing_locked')
            );
        }

        $allowed = [
            'fulfillment_location_id',
            'is_sell_to_market',
            'is_seller_delivery',
            'is_buyer_pickup',
        ];

        $updateData = array_intersect_key($data, array_flip($allowed));

        if (empty($updateData)) {
            throw new RuntimeException(
                __('messages.error_messages.nothing_to_update')
            );
        }

        $listing->update($updateData);

        logActivity(
            'product_listing_flags_updated',
            $user,
            ProductListing::class,
            $listing->id,
            $listing->listing_code
        );

        return $listing;
    }

    /* =========================================================
       ================= UPDATE PACKAGE ========================
       ========================================================= */

    public function updatePackage(User $user, int $packageId, array $data): ProductListingPackage
    {
        if (array_key_exists('sold_qty', $data)) {
            throw new RuntimeException(
                __('messages.error_messages.sold_qty_not_editable')
            );
        }

        $package = ProductListingPackage::findOrFail($packageId);
        $listing = $package->productListingItem->productListing;

        $this->authorize($user, $listing);

        if (isset($data['qty']) && $data['qty'] < $package->sold_qty) {
            throw new RuntimeException(
                __('messages.error_messages.qty_less_than_sold')
            );
        }

        $package->fill(array_intersect_key(
            $data,
            array_flip([
                'qty',
                'pack_price',
                'per_kg_price',
                'discount_amount',
                'discount_type',
            ])
        ));

        $this->recalculatePackageState($package);
        $package->save();

        $this->recalculateListingState($listing);

        logActivity(
            'product_listing_package_updated',
            $user,
            ProductListingPackage::class,
            $package->id,
            $package->listing_code
        );

        return $package;
    }

    /* =========================================================
       ================= CANCEL PACKAGE ========================
       ========================================================= */

    public function deletePackage(User $user, int $packageId, string $reason): void
    {
        if (empty($reason)) {
            throw new RuntimeException(
                __('messages.error_messages.reason_required')
            );
        }

        $package = ProductListingPackage::findOrFail($packageId);
        $listing = $package->productListingItem->productListing;

        $this->authorize($user, $listing);

        if ($package->sold_qty > 0) {
            throw new RuntimeException(
                __('messages.error_messages.package_already_sold')
            );
        }

        DB::transaction(function () use ($package, $listing, $reason) {
            $package->delete();
            $this->recalculateListingState($listing, $reason);
        });
    }

    /* =========================================================
       ================= CANCEL LISTING ========================
       ========================================================= */

    public function cancelListing(User $user, int $listingId, string $reason): void
    {
        if (empty($reason)) {
            throw new RuntimeException(
                __('messages.error_messages.reason_required')
            );
        }

        $listing = ProductListing::findOrFail($listingId);
        $this->authorize($user, $listing);

        $hasSold = ProductListingPackage::whereHas(
            'productListingItem',
            fn($q) => $q->where('product_listing_id', $listing->id)
        )->where('sold_qty', '>', 0)->exists();

        if ($hasSold) {
            throw new RuntimeException(
                __('messages.error_messages.listing_has_sales')
            );
        }

        $listing->update([
            'is_active' => false,
            'is_expired' => true,
            'inactive_reason' => $reason,
            'expires_at' => now(),
        ]);
    }

    /* =========================================================
       ================= PACKAGE STATE =========================
       ========================================================= */

    protected function recalculatePackageState(ProductListingPackage $package): void
    {
        $remaining = $package->qty - $package->sold_qty;

        $package->is_sold = $remaining <= 0;
        $package->is_partial = $package->sold_qty > 0 && $remaining > 0;
        // is_locked untouched (transactional)
    }

    /* =========================================================
       ================= LISTING STATE =========================
       ========================================================= */

    protected function recalculateListingState(
        ProductListing $listing,
        ?string $reason = null
    ): void {

        // 🔒 TERMINAL STATES → DO NOTHING
        if ($listing->is_sold || !$listing->is_active || $listing->is_expired) {
            throw new RuntimeException(
                __('messages.error_messages.listing_terminal_state')
            );
            return;
        }

        $available = ProductListingPackage::whereHas(
            'productListingItem',
            fn($q) => $q->where('product_listing_id', $listing->id)
        )->whereRaw('qty > sold_qty')->count();

        $sold = ProductListingPackage::whereHas(
            'productListingItem',
            fn($q) => $q->where('product_listing_id', $listing->id)
        )->whereRaw('sold_qty >= qty')->count();

        // ✅ CASE 1: Still sellable
        if ($available > 0) {
            $listing->update([
                'is_active' => true,
                'is_sold' => false,
                'is_partial' => $sold > 0,
                'is_expired' => false,
            ]);
            return;
        }

        // ✅ CASE 2: Fully sold out
        if ($sold > 0) {
            $listing->update([
                'is_active' => true,
                'is_sold' => true,
                'is_partial' => false,
                'is_expired' => false,
            ]);
            return;
        }

        // ✅ CASE 3: Nothing sold, nothing available → cancelled
        $listing->update([
            'is_active' => false,
            'is_expired' => true,
            'is_sold' => false,
            'is_partial' => false,
            'inactive_reason' => $reason
                ?? __('messages.error_messages.no_packages_left'),
            'expires_at' => now(),
        ]);
    }

    /* =========================================================
       ================= AUTH / HELPERS ========================
       ========================================================= */

    protected function authorize(User $actor, ProductListing $listing): void
    {
        if (
            !$actor->isAdminManagement() &&
            $listing->seller_id !== $actor->id
        ) {
            throw new RuntimeException(
                __('messages.error_messages.unauthorized_action')
            );
        }
    }

    protected function onlyFillable($model, array $data): array
    {
        return array_intersect_key(
            $data,
            array_flip($model->getFillable())
        );
    }
}
