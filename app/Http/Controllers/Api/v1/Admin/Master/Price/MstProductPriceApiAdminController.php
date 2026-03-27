<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Price;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\Price\MstProductPrice;
use Illuminate\Http\Request;

class MstProductPriceApiAdminController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $priceDate = $request->input('price_date') ?? null;

        $productPrices = MstProductPrice::with(['product', 'market', 'depot'])
            ->when($priceDate, function ($query, $priceDate) {
                $query->where('price_date', $priceDate);
            })
            ->latest()->get();

        return $this->successResponse(__('messages.success_messages.success_get'), $productPrices, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'product_id' => 'required|exists:mst_products,id',
            'price_date' => 'required|date',
            'price' => 'required|numeric',
            'max_price' => 'nullable|numeric',
            'min_price' => 'nullable|numeric',

            // Future Use
            'market_id' => 'nullable|exists:mst_markets,id',
            'depot_id' => 'nullable|exists:mst_depots,id',
        ]);

        $mstProductPrice = MstProductPrice::create($request->all());

        // Log activity
        logActivity(
            'product_price_created',        // EVENT
            $request->user(),                       // ACTOR
            MstProductPrice::class,         // SUBJECT TYPE
            $mstProductPrice->id,           // SUBJECT ID
            $mstProductPrice->price_date, // SUBJECT CODE
            [                                       // META
                'product_id' => $mstProductPrice->product_id,
                'price' => $mstProductPrice->price,
            ]
        );

        return $this->successResponse(__('messages.success_messages.success_create'), $mstProductPrice, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MstProductPrice $mstProductPrice)
    {
        //

        $mstProductPrice->load(['product', 'market', 'depot']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MstProductPrice $mstProductPrice)
    {
        //

        $request->validate([
            'product_id' => 'required|exists:mst_products,id',
            'price_date' => 'required|date',
            'price' => 'required|numeric',
            'max_price' => 'nullable|numeric',
            'min_price' => 'nullable|numeric',

            // Future Use
            'market_id' => 'nullable|exists:mst_markets,id',
            'depot_id' => 'nullable|exists:mst_depots,id',
        ]);

        $mstProductPrice->update($request->all());

        // Log activity
        logActivity(
            'product_price_updated',        // EVENT
            $request->user(),                       // ACTOR
            MstProductPrice::class,         // SUBJECT TYPE
            $mstProductPrice->id,           // SUBJECT ID
            $mstProductPrice->price_date, // SUBJECT CODE
            [                                       // META
                'product_id' => $mstProductPrice->product_id,
                'price' => $mstProductPrice->price,
            ]
        );

        return $this->successResponse(__('messages.success_messages.success_update'), $mstProductPrice, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MstProductPrice $mstProductPrice)
    {
        //

        // Log activity
        logActivity(
            'product_price_deleted',        // EVENT
            request()->user(),                       // ACTOR
            MstProductPrice::class,         // SUBJECT TYPE
            $mstProductPrice->id,           // SUBJECT ID
            $mstProductPrice->price_date, // SUBJECT CODE
            [                                       // META
                'product_id' => $mstProductPrice->product_id,
                'price' => $mstProductPrice->price,
            ]
        );


        $mstProductPrice->delete();

        return $this->successResponse(__('messages.success_messages.success_delete'), null, 200);
    }
}
