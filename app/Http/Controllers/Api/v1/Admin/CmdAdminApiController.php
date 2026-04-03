<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class CmdAdminApiController extends ApiResponseWithAdminAuthController
{
    //


    // Run All Commands here regarding cut off or settlement or invoice generations

    /**
     *  Cutoff Commands
     */

    public function cmdCutoffSeller(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->filled('start_date') ? $request->input('start_date') : null;
        $endDate = $request->filled('end_date') ? $request->input('end_date') : null;

        $command = "cutoff:seller {$startDate} {$endDate}";

        // Log activity
        logActivity(
            'cmd_cutoff_seller', // ACTIVITY TYPE
            $request->user(),       // ACTOR (who did it)
            null,       // SUBJECT TYPE (what was affected)
            null,              // SUBJECT ID
            null,       // SUBJECT CODE (human readable)
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );


        try {
            Artisan::call($command);
            $output = Artisan::output();
            return $this->showSuccessMessage($output);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to execute cutoff command: ' . $e->getMessage(), 500);
        }
    }

    public function cmdCutoffBuyer(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->filled('start_date') ? $request->input('start_date') : null;
        $endDate = $request->filled('end_date') ? $request->input('end_date') : null;

        $command = "cutoff:buyer {$startDate} {$endDate}";

        // Log activity
        logActivity(
            'cmd_cutoff_buyer', // ACTIVITY TYPE
            $request->user(),       // ACTOR (who did it)
            null,       // SUBJECT TYPE (what was affected)
            null,              // SUBJECT ID
            null,       // SUBJECT CODE (human readable)
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );


        try {
            Artisan::call($command);
            $output = Artisan::output();
            return $this->showSuccessMessage($output);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to execute cutoff command: ' . $e->getMessage(), 500);
        }
    }





    // 1. Product Listing Cutoff Command

    public function cmdCutoffProductListing(Request $request)
    {

        return $this->showErrorMessage('This command is currently disabled.', 503);


        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->filled('start_date') ? $request->input('start_date') : null;
        $endDate = $request->filled('end_date') ? $request->input('end_date') : null;


        $command = "cutoff:product-listing {$startDate} {$endDate}";

        // Log activity
        logActivity(
            'cmd_cutoff_product_listing', // ACTIVITY TYPE
            $request->user(),       // ACTOR (who did it)
            null,       // SUBJECT TYPE (what was affected)
            null,              // SUBJECT ID
            null,       // SUBJECT CODE (human readable)
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );


        try {
            Artisan::call($command);
            $output = Artisan::output();




            return $this->showSuccessMessage($output);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to execute cutoff command: ' . $e->getMessage(), 500);
        }
    }


    /**
     *  Accounting Commands
     */

    public function cmdAccountingOrder(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->filled('start_date') ? $request->input('start_date') : null;
        $endDate = $request->filled('end_date') ? $request->input('end_date') : null;


        $command = "accounting:order {$startDate} {$endDate}";


        // Log activity
        logActivity(
            'cmd_accounting_order', // ACTIVITY TYPE
            $request->user(),       // ACTOR (who did it)
            null,       // SUBJECT TYPE (what was affected)
            null,              // SUBJECT ID
            null,       // SUBJECT CODE (human readable)
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );

        try {
            Artisan::call($command);
            $output = Artisan::output();
            return $this->showSuccessMessage($output);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to execute accounting order command: ' . $e->getMessage(), 500);
        }
    }

    public function cmdAccountingMarketOrder(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->filled('start_date') ? $request->input('start_date') : null;
        $endDate = $request->filled('end_date') ? $request->input('end_date') : null;


        $command = "accounting:market-order {$startDate} {$endDate}";

        // Log activity
        logActivity(
            'cmd_accounting_market_order', // ACTIVITY TYPE
            $request->user(),       // ACTOR (who did it)
            null,       // SUBJECT TYPE (what was affected)
            null,              // SUBJECT ID
            null,       // SUBJECT CODE (human readable)
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );

        try {
            Artisan::call($command);
            $output = Artisan::output();
            return $this->showSuccessMessage($output);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to execute accounting market order command: ' . $e->getMessage(), 500);
        }
    }


    public function cmdAccountingInvoice(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->filled('start_date') ? $request->input('start_date') : null;
        $endDate = $request->filled('end_date') ? $request->input('end_date') : null;


        $command = "accounting:invoice {$startDate} {$endDate}";

        // Log activity
        logActivity(
            'cmd_accounting_invoice', // ACTIVITY TYPE
            $request->user(),       // ACTOR (who did it)
            null,       // SUBJECT TYPE (what was affected)
            null,              // SUBJECT ID
            null,       // SUBJECT CODE (human readable)
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );

        try {
            Artisan::call($command);
            $output = Artisan::output();
            return $this->showSuccessMessage($output);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to execute accounting invoice command: ' . $e->getMessage(), 500);
        }
    }




    /**
     *  Invoice Generation Commands
     */

    public function cmdBuyerOrderInvoiceGeneration(Request $request)
    {
        // Log::info('Received request for buyer order invoice generation command', [
        //     'request_data' => $request->all(),
        //     'user_id' => $request->user()->id,
        // ]);


        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_enforce' => 'nullable|boolean',
        ]);

        $startDate = $request->filled('start_date') ? $request->input('start_date') : null;
        $endDate = $request->filled('end_date') ? $request->input('end_date') : null;
        $isEnforce = $request->filled('is_enforce') ? filter_var($request->input('is_enforce'), FILTER_VALIDATE_BOOLEAN) : false;

        // 'invoice:buyer-order
        //                     {startDate?} 
        //                     {endDate?} 
        //                     {isEnforce?}';

        $command = "invoicing:order {$startDate} {$endDate} {$isEnforce}";

        // Log activity
        logActivity(
            'cmd_invoice_buyer_order', // ACTIVITY TYPE
            $request->user(),       // ACTOR (who did it)
            null,       // SUBJECT TYPE (what was affected)
            null,              // SUBJECT ID
            null,       // SUBJECT CODE (human readable)
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_enforce' => $isEnforce,
            ]
        );

        try {
            Artisan::call($command);
            $output = Artisan::output();
            return $this->showSuccessMessage($output);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to execute invoice generation command: ' . $e->getMessage(), 500);
        }
    }


    public function cmdProductListingInvoiceGeneration(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_enforce' => 'nullable|boolean',
        ]);

        $startDate = $request->filled('start_date') ? $request->input('start_date') : null;
        $endDate = $request->filled('end_date') ? $request->input('end_date') : null;
        $isEnforce = $request->filled('is_enforce') ? filter_var($request->input('is_enforce'), FILTER_VALIDATE_BOOLEAN) : false;

        // 'invoice:product-listing
        //                     {startDate?} 
        //                     {endDate?} 
        //                     {isEnforce?}';

        $command = "invoicing:product-listing {$startDate} {$endDate} {$isEnforce}";

        // Log activity
        logActivity(
            'cmd_invoice_product_listing', // ACTIVITY TYPE
            $request->user(),       // ACTOR (who did it)
            null,       // SUBJECT TYPE (what was affected)
            null,              // SUBJECT ID
            null,       // SUBJECT CODE (human readable)
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_enforce' => $isEnforce,
            ]
        );

        try {
            Artisan::call($command);
            $output = Artisan::output();
            return $this->showSuccessMessage($output);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to execute invoice generation command: ' . $e->getMessage(), 500);
        }
    }





    //
}
