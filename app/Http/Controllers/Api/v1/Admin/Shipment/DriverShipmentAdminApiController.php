<?php

namespace App\Http\Controllers\Api\v1\Admin\Shipment;

use App\Enum\Common\Shipment\DriverShipmentStatusEnum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Enum\User\UserRoleEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Common\Shipment\Shipment;
use App\Models\Delivery\DriverShipment;
use App\Models\Delivery\DriverVehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DriverShipmentAdminApiController extends ApiResponseWithAdminAuthController
{
    /*
    |--------------------------------------------------------------------------
    | GET AVAILABLE DRIVERS
    |--------------------------------------------------------------------------
    */
    public function getDriversWithAvailableVehicles()
    {
        $depotsIds = Shipment::where('status', ShipmentStatusEnum::PENDING->value)
            ->pluck('origin_depot_id')
            ->merge(
                Shipment::where('status', ShipmentStatusEnum::PENDING->value)
                    ->pluck('destination_depot_id')
            )
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        // Log::info('Depots IDs for available drivers: ' . implode(', ', $depotsIds));

        $drivers = DriverVehicle::with([
            'driver.depots',
            'driver.driverLocation',
        ])
            ->active()

            // ->whereHas('driver.depots', function ($q) use ($depotsIds) {
            //     $q->whereIn('depot_id', $depotsIds);
            // })
            ->whereHas('driver', function ($q) {
                $q->where('role', UserRoleEnum::DELIVERY->value)
                    ->where('is_active', true)
                    ->where('is_available_for_delivery', true);
            })
            ->get();

        return $this->successResponse(__('messages.success_messages.success_get'), $drivers);
    }

    /*
    |--------------------------------------------------------------------------
    | LIST DRIVER SHIPMENTS (ADMIN)
    |--------------------------------------------------------------------------
    */
    public function getDriverShipments(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'driver_id' => 'nullable|exists:users,id',
            'shipment_number' => 'nullable|string',
            'status' => 'nullable|in:'.implode(',', DriverShipmentStatusEnum::casesAsValues()),
        ]);

        $query = DriverShipment::with([
            'driver',
            'driver.driverLocation',
            'driverVehicle',
            'shipment.shipmentPackages',
            'assignedBy',
        ]);

        $start = $request->filled('start_date')
            ? now()->parse($request->start_date)->startOfDay()
            : now()->subDay()->startOfDay();

        $end = $request->filled('end_date')
            ? now()->parse($request->end_date)->endOfDay()
            : now()->endOfDay();

        $query->whereBetween('assigned_at', [$start, $end]);

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }

        if ($request->filled('shipment_number')) {
            $query->whereHas(
                'shipment',
                fn ($q) => $q->where('shipment_number', 'like', '%'.$request->shipment_number.'%')
            );
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            $query->orderByDesc('assigned_at')->get()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ASSIGN DRIVER
    |--------------------------------------------------------------------------
    */
    public function assignDriver(Request $request)
    {
        $request->validate([
            'shipment_id' => 'required|exists:shipments,id',
            // 'driver_id' => 'required|exists:users,id',
            'driver_vehicle_id' => 'required|exists:driver_vehicles,id',
        ]);

        $vehicle = DriverVehicle::where('id', $request->driver_vehicle_id)
            ->where('is_available_for_delivery', true)
            ->whereHas('driver', function ($q) {
                $q->where('role', UserRoleEnum::DELIVERY->value)
                    ->where('is_active', true)
                    ->where('is_available_for_delivery', true);
            })
            ->firstOrFail();

        $shipment = Shipment::findOrFail($request->shipment_id);

        // Check if shipment already assigned

        $driverShipment = DriverShipment::create([
            'shipment_id' => $request->shipment_id,
            'driver_id' => $vehicle->driver_id,
            'driver_vehicle_id' => $vehicle->id,
            'assigned_by' => request()->user()->id,
            'assigned_at' => now(),
            'vehicle_number' => $vehicle->license_plate_number,
            'status' => DriverShipmentStatusEnum::REQUESTED->value,
        ]);

        // once done change status of shipment to assigned if not already
        if ($shipment->status !== ShipmentStatusEnum::ASSIGNED->value) {
            $shipment->update(['status' => ShipmentStatusEnum::ASSIGNED->value]);
        }

        //

        $this->sendAssignedNotification($driverShipment);

        return $this->showSuccessMessage(__('messages.success_messages.success_create'));
    }

    /*
    |--------------------------------------------------------------------------
    | CHANGE DRIVER (ONLY BEFORE ACCEPT)
    |--------------------------------------------------------------------------
    */
    public function changeDriver(Request $request, DriverShipment $driverShipment)
    {
        $request->validate([
            // 'driver_id' => 'required|exists:users,id',
            'driver_vehicle_id' => 'required|exists:driver_vehicles,id',
        ]);

        // same

        $vehicle = DriverVehicle::where('id', $request->driver_vehicle_id)
            ->where('is_available_for_delivery', true)
            ->whereHas('driver', function ($q) {
                $q->where('role', UserRoleEnum::DELIVERY->value)
                    ->where('is_active', true)
                    ->where('is_available_for_delivery', true);
            })
            ->firstOrFail();

        if ($driverShipment->accepted_at || $vehicle->driver_id === $driverShipment->driver_id) {
            return $this->errorResponse('Driver already accepted.', 422);
        }

        $driverShipment->update([
            'driver_id' => $vehicle->driver_id,
            'driver_vehicle_id' => $vehicle->id,
            'vehicle_number' => $vehicle->license_plate_number,
            'assigned_by' => request()->user()->id,
            'assigned_at' => now(),
        ]);

        $this->sendAssignedNotification($driverShipment);

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }

    /*
    |--------------------------------------------------------------------------
    | CANCEL ASSIGNMENT
    |--------------------------------------------------------------------------
    */
    public function cancel(DriverShipment $driverShipment)
    {
        if ($driverShipment->started_at) {
            return $this->errorResponse('Cannot cancel after start.', 422);
        }

        if (in_array($driverShipment->status, [DriverShipmentStatusEnum::CANCELLED->value, DriverShipmentStatusEnum::REJECTED->value])) {
            return $this->errorResponse('Already cancelled or rejected.', 422);
        }

        DB::transaction(function () use ($driverShipment) {
            $driverShipment->update([
                'status' => DriverShipmentStatusEnum::CANCELLED->value,
            ]);

            // Shipment make available for other assignment if needed
            $shipment = $driverShipment->shipment;
            $shipment->update(['status' => ShipmentStatusEnum::PENDING->value]);
        });

        // $driverShipment->delete();

        return $this->showSuccessMessage(__('messages.success_messages.success_delete'));
    }

    protected function sendAssignedNotification(DriverShipment $driverShipment)
    {
        // later push notification
    }
}
