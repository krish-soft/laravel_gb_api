<?php

namespace App\Services\Common\Shipment;

use App\Models\Common\Shipment\Shipment;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Common\Shipment\ShipmentPackageGroup;
use Illuminate\Support\Facades\DB;
use Exception;

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
            throw new Exception("Package already processed. Action not allowed.");
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
            throw new Exception("Cannot mix pickup and dispatch shipments.");
        }

        if ($from->shipment_date != $to->shipment_date) {
            throw new Exception("Shipment date mismatch.");
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
        | STEP 1 — FETCH PACKAGES NOT IN THIS SHIPMENT TYPE
        |--------------------------------------------------------------------------
        */

            $packages = ShipmentPackage::query()

                // 🚫 block only SAME shipment_type grouping
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
        | STEP 2 — BUILD ROUTE GROUPS
        |--------------------------------------------------------------------------
        */

            $routeGroups = $packages->groupBy(function ($p) use ($shipmentType) {

                if ($shipmentType === 'pickup') {
                    return implode('|', [
                        $p->seller_id,
                        $p->pickup_fulfillment_location_id,
                        $p->pickup_depot_id
                    ]);
                }

                return implode('|', [
                    $p->shipping_depot_id,
                    $p->buyer_id
                ]);
            });

            $createdShipments = [];

            /*
        |--------------------------------------------------------------------------
        | STEP 3 — LOOP ROUTES
        |--------------------------------------------------------------------------
        */

            foreach ($routeGroups as $routeKey => $collection) {

                $first = $collection->first();

                /*
            |--------------------------------------------------------------------------
            | 🔥 FIND EXISTING SHIPMENT FIRST
            |--------------------------------------------------------------------------
            */

                $existingShipment = Shipment::query()
                    ->where('shipment_type', $shipmentType)
                    ->when($shipmentType === 'pickup', function ($q) use ($first) {
                        $q->where('seller_id', $first->seller_id)
                            ->where('origin_id', $first->pickup_fulfillment_location_id)
                            ->where('destination_id', $first->pickup_depot_id);
                    })
                    ->when($shipmentType === 'dispatch', function ($q) use ($first) {
                        $q->where('buyer_id', $first->buyer_id)
                            ->where('origin_id', $first->shipping_depot_id)
                            ->where('destination_id', $first->buyer_id);
                    })
                    ->first();

                /*
            |--------------------------------------------------------------------------
            | CREATE ONLY IF NOT EXIST
            |--------------------------------------------------------------------------
            */

                if (!$existingShipment) {

                    $shipmentData = [
                        'shipment_type' => $shipmentType,
                        'shipment_date' => now()->toDateString(),
                        'status'        => 'grouped',
                    ];

                    if ($shipmentType === 'pickup') {
                        $shipmentData['seller_id'] = $first->seller_id;
                        $shipmentData['origin_type'] = 'fulfillment_location';
                        $shipmentData['origin_id']   = $first->pickup_fulfillment_location_id;
                        $shipmentData['destination_type'] = 'depot';
                        $shipmentData['destination_id']   = $first->pickup_depot_id;
                    } else {
                        $shipmentData['buyer_id'] = $first->buyer_id;
                        $shipmentData['origin_type'] = 'depot';
                        $shipmentData['origin_id']   = $first->shipping_depot_id;
                        $shipmentData['destination_type'] = 'buyer';
                        $shipmentData['destination_id']   = $first->buyer_id;
                    }

                    $existingShipment = Shipment::create($shipmentData);
                }

                /*
            |--------------------------------------------------------------------------
            | STEP 4 — ADD PACKAGES INTO EXISTING SHIPMENT
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
                throw new \Exception("Nothing to split");
            }

            $originalShipment = Shipment::findOrFail($rows->first()->shipment_id);

            // ❌ DO NOT allow split if already assigned
            if (in_array($originalShipment->status, ['assigned', 'in_transit', 'completed'])) {
                throw new \Exception("Shipment locked. Cannot split.");
            }

            /*
        |--------------------------------------------------------------------------
        | CREATE NEW SHIPMENT (CLONE ROUTE)
        |--------------------------------------------------------------------------
        */

            $newShipment = Shipment::create([
                'shipment_type'   => $originalShipment->shipment_type,
                'shipment_date'   => $originalShipment->shipment_date,
                'seller_id'       => $originalShipment->seller_id,
                'buyer_id'        => $originalShipment->buyer_id,
                'origin_type'     => $originalShipment->origin_type,
                'origin_id'       => $originalShipment->origin_id,
                'destination_type' => $originalShipment->destination_type,
                'destination_id'  => $originalShipment->destination_id,
                'status'          => 'grouped',
            ]);

            $newGroupNumber = ShipmentPackageGroup::generateUniqueGroupNumber();

            /*
        |--------------------------------------------------------------------------
        | MOVE PACKAGES TO NEW SHIPMENT
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
                throw new \Exception("Source shipment locked");
            }

            if (in_array($toShipment->status, ['assigned', 'in_transit', 'completed'])) {
                throw new \Exception("Target shipment locked");
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
