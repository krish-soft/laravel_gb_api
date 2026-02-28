<?php

namespace App\Services\Common\Shipment;

use App\Models\Common\Shipment\Shipment;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Common\Shipment\ShipmentPackageGroup;
use Illuminate\Support\Facades\DB;
use Exception;
use RuntimeException;

class ShipmentService
{

    /*
    |--------------------------------------------------------------------------
    | VALIDATE PACKAGE CAN BE MODIFIED
    |--------------------------------------------------------------------------
    */
    private function assertEditablePackage(ShipmentPackage $package)
    {
        if (in_array($package->status, [
            'in_transit',
            'delivered',
            'cancelled',
            'returned'
        ])) {
            throw new RuntimeException("Package already processed. Action not allowed.");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE SAME SHIPMENT TYPE + SAME DAY
    |--------------------------------------------------------------------------
    */
    private function assertSameShipmentContext(Shipment $from, Shipment $to)
    {
        if ($from->shipment_type !== $to->shipment_type) {
            throw new RuntimeException("Cannot mix pickup and dispatch shipments.");
        }

        if ($from->shipment_date != $to->shipment_date) {
            throw new RuntimeException("Shipment date mismatch.");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE SHIPMENT + GROUPING
    |--------------------------------------------------------------------------
    */
    public function createShipmentAndGroups(string $shipmentType): array
    {
        return DB::transaction(function () use ($shipmentType) {

            /*
        |--------------------------------------------------------------------------
        | FETCH PACKAGES
        |--------------------------------------------------------------------------
        */

            $packages = ShipmentPackage::query()

                ->whereNotExists(function ($q) use ($shipmentType) {
                    $q->select(DB::raw(1))
                        ->from('shipment_package_groups as spg')
                        ->join('shipments as s', 's.id', '=', 'spg.shipment_id')
                        ->whereColumn('spg.shipment_package_id', 'shipment_packages.id')
                        ->where('s.shipment_type', $shipmentType)
                        ->whereNull('spg.deleted_at')
                        ->whereNull('s.deleted_at');
                })

                ->whereNotIn('status', ['delivered', 'cancelled', 'returned'])
                ->get();

            if ($packages->isEmpty()) {
                return [];
            }

            /*
        |--------------------------------------------------------------------------
        | ROUTE GROUPING
        |--------------------------------------------------------------------------
        */

            $routeGroups = $packages->groupBy(function ($p) use ($shipmentType) {

                if ($shipmentType === 'pickup') {

                    if ($p->is_seller_dropoff) {
                        return '__SKIP__';
                    }

                    return implode('|', [
                        $p->seller_id,
                        $p->pickup_fulfillment_location_id,
                        $p->pickup_depot_id
                    ]);
                }

                if ($shipmentType === 'transfer') {

                    // 🔥 HARD STOP FOR SAME DEPOT
                    if ((int)$p->pickup_depot_id === (int)$p->shipping_depot_id) {
                        return '__SKIP__';
                    }

                    return implode('|', [
                        $p->pickup_depot_id,
                        $p->shipping_depot_id
                    ]);
                }

                if ($shipmentType === 'dispatch') {

                    if ($p->is_buyer_pickup) {
                        return '__SKIP__';
                    }

                    return implode('|', [
                        $p->shipping_depot_id,
                        $p->buyer_id
                    ]);
                }

                return '__SKIP__';
            });

            // 🔥 REMOVE SKIPPED TRANSFER KEYS ONLY
            $routeGroups = $routeGroups->reject(function ($v, $key) {
                return $key === '__SKIP__';
            });

            $createdShipments = [];

            /*
        |--------------------------------------------------------------------------
        | CREATE SHIPMENTS
        |--------------------------------------------------------------------------
        */

            foreach ($routeGroups as $collection) {

                if ($collection->isEmpty()) {
                    continue;
                }

                $first = $collection->first();

                /*
            |--------------------------------------------------------------------------
            | FIND EXISTING SHIPMENT
            |--------------------------------------------------------------------------
            */

                $existingShipment = Shipment::query()
                    ->where('shipment_type', $shipmentType)

                    ->when($shipmentType === 'pickup', function ($q) use ($first) {
                        $q->where('seller_id', $first->seller_id)
                            ->where('origin_flmnt_location_id', $first->pickup_fulfillment_location_id)
                            ->where('destination_depot_id', $first->pickup_depot_id);
                    })

                    ->when($shipmentType === 'transfer', function ($q) use ($first) {
                        $q->where('origin_depot_id', $first->pickup_depot_id)
                            ->where('destination_depot_id', $first->shipping_depot_id);
                    })

                    ->when($shipmentType === 'dispatch', function ($q) use ($first) {
                        $q->where('buyer_id', $first->buyer_id)
                            ->where('origin_depot_id', $first->shipping_depot_id)
                            ->where('destination_flmnt_location_id', $first->shipping_fulfillment_location_id);
                    })

                    ->first();

                /*
            |--------------------------------------------------------------------------
            | CREATE SHIPMENT IF NOT EXIST
            |--------------------------------------------------------------------------
            */

                if (!$existingShipment) {

                    $data = [
                        'shipment_type' => $shipmentType,
                        'shipment_date' => now()->toDateString(),
                        'status'        => 'grouped',
                    ];

                    if ($shipmentType === 'pickup') {

                        $data['seller_id'] = $first->seller_id;
                        $data['origin_type'] = 'fulfillment_location';
                        $data['origin_flmnt_location_id'] = $first->pickup_fulfillment_location_id;
                        $data['destination_type'] = 'depot';
                        $data['destination_depot_id'] = $first->pickup_depot_id;
                    } elseif ($shipmentType === 'transfer') {

                        $data['origin_type'] = 'depot';
                        $data['origin_depot_id'] = $first->pickup_depot_id;
                        $data['destination_type'] = 'depot';
                        $data['destination_depot_id'] = $first->shipping_depot_id;
                    } elseif ($shipmentType === 'dispatch') {

                        $data['buyer_id'] = $first->buyer_id;
                        $data['origin_type'] = 'depot';
                        $data['origin_depot_id'] = $first->shipping_depot_id;
                        $data['destination_type'] = 'fulfillment_location';
                        $data['destination_flmnt_location_id'] = $first->shipping_fulfillment_location_id;
                    }

                    $existingShipment = Shipment::create($data);
                }

                /*
            |--------------------------------------------------------------------------
            | CREATE GROUP
            |--------------------------------------------------------------------------
            */

                $groupNumber = ShipmentPackageGroup::generateUniqueGroupNumber();

                foreach ($collection as $pkg) {

                    ShipmentPackageGroup::create([
                        'group_number'        => $groupNumber,
                        'shipment_id'         => $existingShipment->id,
                        'shipment_package_id' => $pkg->id,
                        'buyer_id'            => $pkg->buyer_id,
                        'seller_id'           => $pkg->seller_id,
                    ]);
                }

                $createdShipments[] = $existingShipment;
            }

            return $createdShipments;
        });
    }

    // Original but transfer not there
    // public function createShipmentAndGroups(string $shipmentType): array
    // {
    //     return DB::transaction(function () use ($shipmentType) {

    //         /*
    //     |--------------------------------------------------------------------------
    //     | STEP 1 — FETCH PACKAGES NOT IN THIS SHIPMENT TYPE
    //     |--------------------------------------------------------------------------
    //     */

    //         $packages = ShipmentPackage::query()

    //             // 🚫 block only SAME shipment_type grouping
    //             ->whereNotExists(function ($q) use ($shipmentType) {
    //                 $q->select(DB::raw(1))
    //                     ->from('shipment_package_groups as spg')
    //                     ->join('shipments as s', 's.id', '=', 'spg.shipment_id')
    //                     ->whereColumn('spg.shipment_package_id', 'shipment_packages.id')
    //                     ->where('s.shipment_type', $shipmentType)
    //                     ->whereNull('spg.deleted_at')
    //                     ->whereNull('s.deleted_at');
    //             })

    //             ->whereNotIn('status', ['delivered', 'cancelled', 'returned'])

    //             ->get();

    //         if ($packages->isEmpty()) {
    //             return [];
    //         }

    //         /*
    //     |--------------------------------------------------------------------------
    //     | STEP 2 — BUILD ROUTE GROUPS
    //     |--------------------------------------------------------------------------
    //     */

    //         $routeGroups = $packages->groupBy(function ($p) use ($shipmentType) {

    //             if ($shipmentType === 'pickup') {
    //                 return implode('|', [
    //                     $p->seller_id,
    //                     $p->pickup_fulfillment_location_id,
    //                     $p->shipping_fulfillment_location_id,
    //                     $p->pickup_depot_id
    //                 ]);
    //             }

    //             return implode('|', [
    //                 $p->shipping_depot_id,
    //                 $p->buyer_id
    //             ]);
    //         });

    //         $createdShipments = [];

    //         /*
    //     |--------------------------------------------------------------------------
    //     | STEP 3 — LOOP ROUTES
    //     |--------------------------------------------------------------------------
    //     */

    //         foreach ($routeGroups as $routeKey => $collection) {

    //             $first = $collection->first();

    //             /*
    //         |--------------------------------------------------------------------------
    //         | 🔥 FIND EXISTING SHIPMENT FIRST
    //         |--------------------------------------------------------------------------
    //         */

    //             $existingShipment = Shipment::query()
    //                 ->where('shipment_type', $shipmentType)
    //                 ->when($shipmentType === 'pickup', function ($q) use ($first) {
    //                     $q->where('seller_id', $first->seller_id)
    //                         ->where('origin_flmnt_location_id', $first->pickup_fulfillment_location_id)
    //                         ->where('destination_depot_id', $first->pickup_depot_id);
    //                 })
    //                 ->when($shipmentType === 'dispatch', function ($q) use ($first) {
    //                     $q->where('buyer_id', $first->buyer_id)
    //                         ->where('origin_depot_id', $first->shipping_depot_id)
    //                         ->where('destination_flmnt_location_id', $first->shipping_fulfillment_location_id);
    //                 })
    //                 ->first();

    //             /*
    //         |--------------------------------------------------------------------------
    //         | CREATE ONLY IF NOT EXIST
    //         |--------------------------------------------------------------------------
    //         */

    //             if (!$existingShipment) {

    //                 $shipmentData = [
    //                     'shipment_type' => $shipmentType,
    //                     'shipment_date' => now()->toDateString(),
    //                     'status'        => 'grouped',
    //                 ];

    //                 if ($shipmentType === 'pickup') {
    //                     $shipmentData['seller_id'] = $first->seller_id;
    //                     $shipmentData['origin_type'] = 'fulfillment_location';
    //                     $shipmentData['origin_flmnt_location_id']   = $first->pickup_fulfillment_location_id;
    //                     $shipmentData['destination_type'] = 'depot';
    //                     $shipmentData['destination_depot_id']   = $first->pickup_depot_id;
    //                 } else {
    //                     $shipmentData['buyer_id'] = $first->buyer_id;
    //                     $shipmentData['origin_type'] = 'depot';
    //                     $shipmentData['origin_depot_id']   = $first->shipping_depot_id;
    //                     $shipmentData['destination_type'] = 'fulfillment_location';
    //                     $shipmentData['destination_flmnt_location_id']   = $first->shipping_fulfillment_location_id;
    //                 }

    //                 $existingShipment = Shipment::create($shipmentData);
    //             }

    //             /*
    //         |--------------------------------------------------------------------------
    //         | STEP 4 — ADD PACKAGES INTO EXISTING SHIPMENT
    //         |--------------------------------------------------------------------------
    //         */

    //             $groupNumber = ShipmentPackageGroup::generateUniqueGroupNumber();

    //             foreach ($collection as $pkg) {

    //                 ShipmentPackageGroup::create([
    //                     'group_number'        => $groupNumber,
    //                     'shipment_id'         => $existingShipment->id,
    //                     'shipment_package_id' => $pkg->id,
    //                     'buyer_id'            => $pkg->buyer_id,
    //                     'seller_id'           => $pkg->seller_id,
    //                 ]);
    //             }

    //             $createdShipments[] = $existingShipment;
    //         }

    //         return $createdShipments;
    //     });
    // }


    /*
    |--------------------------------------------------------------------------
    | SPLIT GROUP (FIXED)
    |--------------------------------------------------------------------------
    */
    public function splitGroup(string $groupNumber, array $packageIds)
    {
        return DB::transaction(function () use ($groupNumber, $packageIds) {

            $rows = ShipmentPackageGroup::query()
                ->where('group_number', $groupNumber)
                ->whereIn('shipment_package_id', $packageIds)
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                throw new \RuntimeException("Nothing to split");
            }

            $originalShipment = Shipment::findOrFail(
                $rows->first()->shipment_id
            );

            // ❌ DO NOT allow split if already assigned
            if (in_array($originalShipment->status, ['assigned', 'in_transit', 'completed'])) {
                throw new RuntimeException("Shipment locked. Cannot split.");
            }

            /*
        |--------------------------------------------------------------------------
        | ✅ CLONE SHIPMENT EXACTLY (NO AUTO ROUTE CHANGE)
        |--------------------------------------------------------------------------
        */

            $cloneData = $originalShipment->only([
                'shipment_type',
                'shipment_date',
                'seller_id',
                'buyer_id',

                'origin_type',
                'origin_flmnt_location_id',
                'origin_depot_id',

                'destination_type',
                'destination_flmnt_location_id',
                'destination_depot_id',

                'remarks',
            ]);

            // force new grouped status
            $cloneData['status'] = 'grouped';

            // 🔥 IMPORTANT: remove id & timestamps if exist
            unset(
                $cloneData['id'],
                $cloneData['created_at'],
                $cloneData['updated_at'],
                $cloneData['deleted_at']
            );

            $newShipment = Shipment::create($cloneData);

            $newGroupNumber = ShipmentPackageGroup::generateUniqueGroupNumber();

            /*
        |--------------------------------------------------------------------------
        | MOVE PACKAGES
        |--------------------------------------------------------------------------
        */

            ShipmentPackageGroup::where('group_number', $groupNumber)
                ->whereIn('shipment_package_id', $packageIds)
                ->update([
                    'shipment_id'  => $newShipment->id,
                    'group_number' => $newGroupNumber,
                    'updated_at'   => now(),
                ]);

            return $newShipment;
        });
    }



    /*
    |--------------------------------------------------------------------------
    | MOVE PACKAGE (REAL MOVE BETWEEN SHIPMENTS)
    |--------------------------------------------------------------------------
    */

    public function movePackage(int $packageId, string $targetGroup)
    {
        return DB::transaction(function () use ($packageId, $targetGroup) {

            $source = ShipmentPackageGroup::with(['shipment', 'shipmentPackage'])
                ->where('shipment_package_id', $packageId)
                ->lockForUpdate()
                ->firstOrFail();

            $target = ShipmentPackageGroup::with('shipment')
                ->where('group_number', $targetGroup)
                ->firstOrFail();

            $this->assertEditablePackage($source->shipmentPackage);
            $this->assertSameShipmentContext($source->shipment, $target->shipment);

            /*
            |--------------------------------------------------------------------------
            | REAL MOVE = CHANGE SHIPMENT + GROUP
            |--------------------------------------------------------------------------
            */

            $source->update([
                'shipment_id' => $target->shipment_id,
                'group_number' => $targetGroup,
            ]);

            return true;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | MERGE GROUPS (SAME SHIPMENT ONLY)
    |--------------------------------------------------------------------------
    */

    public function mergeGroups(string $from, string $to)
    {
        return DB::transaction(function () use ($from, $to) {

            if ($from === $to) {
                return true;
            }

            $toRow = ShipmentPackageGroup::with('shipment')
                ->where('group_number', $to)
                ->firstOrFail();

            $rows = ShipmentPackageGroup::with(['shipment', 'shipmentPackage'])
                ->where('group_number', $from)
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {

                $this->assertEditablePackage($row->shipmentPackage);
                $this->assertSameShipmentContext($row->shipment, $toRow->shipment);
            }

            ShipmentPackageGroup::where('group_number', $from)
                ->update([
                    'shipment_id' => $toRow->shipment_id,
                    'group_number' => $to
                ]);

            return true;
        });
    }


    public function mergeShipments(int $fromShipmentId, int $toShipmentId)
    {
        return DB::transaction(function () use ($fromShipmentId, $toShipmentId) {

            $fromShipment = Shipment::findOrFail($fromShipmentId);
            $toShipment   = Shipment::findOrFail($toShipmentId);

            // safety checks
            $this->assertSameShipmentContext($fromShipment, $toShipment);

            if (in_array($fromShipment->status, ['assigned', 'in_transit', 'completed'])) {
                throw new RuntimeException("Source shipment locked");
            }

            if (in_array($toShipment->status, ['assigned', 'in_transit', 'completed'])) {
                throw new RuntimeException("Target shipment locked");
            }

            /*
        |--------------------------------------------------------------------------
        | MOVE ALL GROUPS TO TARGET SHIPMENT
        |--------------------------------------------------------------------------
        */

            ShipmentPackageGroup::where('shipment_id', $fromShipment->id)
                ->update([
                    'shipment_id' => $toShipment->id,
                    'updated_at'  => now(),
                ]);

            /*
        |--------------------------------------------------------------------------
        | OPTIONAL: delete empty shipment
        |--------------------------------------------------------------------------
        */

            $fromShipment->delete();

            return true;
        });
    }




    /*
    |--------------------------------------------------------------------------
    | REBUILD
    |--------------------------------------------------------------------------
    */

    public function rebuildGrouping(int $shipmentId)
    {
        return DB::transaction(function () use ($shipmentId) {

            ShipmentPackageGroup::where('shipment_id', $shipmentId)->delete();

            $shipment = Shipment::findOrFail($shipmentId);

            return $this->createShipmentAndGroups($shipment->shipment_type);
        });
    }
}
