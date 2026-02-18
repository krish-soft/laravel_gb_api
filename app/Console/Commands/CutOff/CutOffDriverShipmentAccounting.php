<?php

namespace App\Console\Commands\CutOff;

use App\Enum\Queue\QueueEnum;
use App\Jobs\CutOff\JobCutOffDriverShipmentAccounting;
use App\Jobs\CutOff\JobCutOffOrderAccounting;
use App\Models\Buyer\Order\Order;
use App\Models\Delivery\DriverShipment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class CutOffDriverShipmentAccounting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cut-off:driver-shipment-accounting
                            {startDate?} 
                            {endDate?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cut off driver shipment accounting process.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //

        // we can proceed with driver shipment accounting cutoff around 13 once all shipments reached

        // 1. All Orders

        $startDate = $this->argument('startDate') ?? now()->subDay()->toDateString();
        $endDate   = $this->argument('endDate')   ?? now()->toDateString();

        $this->info("Cutoff from {$startDate} to {$endDate}");


        $driverShipments = DriverShipment::query()
            ->whereBetween('created_at', [
                \Carbon\Carbon::parse($startDate)->startOfDay(),
                \Carbon\Carbon::parse($endDate)->endOfDay(),
            ])
            ->orderBy('driver_id')
            ->orderBy('id')
            ->get();


        if ($driverShipments->isEmpty()) {
            $this->warn('No shipments eligible for cutoff.');
            return;
        }


        $groupedByDrivers = $driverShipments->groupBy('driver_id');

        $jobs = [];

        // FacadesLog::info('CutOff Driver Shipment Accounting - Grouping Shipments by Driver', [
        //     'total_shipments' => $driverShipments->count(),
        //     'drivers_count' => $groupedByDrivers->count(),
        // ]);
        //

        foreach ($groupedByDrivers as $driverId => $driverShipments) {

            $driverShipments->pluck('id')
                ->chunk(10) // batch size per driver
                ->each(function ($chunk) use (&$jobs) {
                    $jobs[] = new JobCutOffDriverShipmentAccounting($chunk->toArray());
                });
        }

        if (empty($jobs)) {
            $this->warn('No jobs generated.');
            return;
        }

        Bus::batch($jobs)
            ->name('CutOff Shipment Accounting DriverShipment Batch (Grouped by Driver)')
            ->onQueue(QueueEnum::ACCOUNTING_CUTOFF->value) // assign entire batch to cutoff queue
            ->dispatch();

        $this->info('Batch dispatched successfully.');
    }
}
