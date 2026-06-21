<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\Accounting;

use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Accounting\PlatformAccountCodeEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Common\Accounting\Account;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountAdminApiController extends ApiResponseWithAdminAuthController
{

    public function summary(Request $request)
    {
        /*
    |--------------------------------------------------------------------------
    | 1️⃣ STATUS CLASSIFICATION
    |--------------------------------------------------------------------------
    */
        $statusCase = "
        CASE
            WHEN available_balance > 0 THEN 'available'
            WHEN hold_balance > 0 THEN 'hold'
            ELSE 'zero'
        END
    ";


        /*
    |--------------------------------------------------------------------------
    | 2️⃣ LOAD PLATFORM ACCOUNTS FROM DB (REAL DATA)
    |--------------------------------------------------------------------------
    */
        $platformAccountsDB = Account::query()
            ->whereIn('owner_type', AccountOwnerTypeEnum::casesAsValues())
            ->select([
                'accnt_code',
                'name',
                'available_balance',
                'hold_balance',
                DB::raw('(available_balance + hold_balance) as total_balance')
            ])
            ->get()
            ->keyBy('accnt_code');


        /*
    |--------------------------------------------------------------------------
    | 3️⃣ ENSURE ALL ENUM PLATFORM ACCOUNTS EXIST (UI PURPOSE)
    |--------------------------------------------------------------------------
    */
        $platformAccounts = collect();

        foreach (PlatformAccountCodeEnum::cases() as $case) {

            $code = $case->value;

            $account = $platformAccountsDB[$code] ?? null;

            $platformAccounts->push([
                'accnt_code'        => $code,
                'name'              => $account->name ?? $code,
                'available_balance' => $account->available_balance ?? 0,
                'hold_balance'      => $account->hold_balance ?? 0,
                'total_balance'     => ($account->available_balance ?? 0)
                    + ($account->hold_balance ?? 0),
            ]);
        }


        /*
    |--------------------------------------------------------------------------
    | 4️⃣ PLATFORM SUMMARY (ROLE + DIRECTION)
    |--------------------------------------------------------------------------
    */
        $platformSummary = $platformAccounts->map(function ($row) {

            $balance = $row['total_balance'];

            return [
                'account_code' => $row['accnt_code'],
                'balance'      => $balance,
                'direction'    => $balance < 0 ? 'receivable' : 'payable',
                'type' => match ($row['accnt_code']) {
                    // PlatformAccountCodeEnum::PLATFORM_REVENUE->value => 'income',
                    // PlatformAccountCodeEnum::PLATFORM_TAX->value => 'government_payable',
                    PlatformAccountCodeEnum::PLATFORM_CLEARING->value => 'escrow',
                    PlatformAccountCodeEnum::CASH->value,
                    PlatformAccountCodeEnum::BANK_01->value,
                    PlatformAccountCodeEnum::BANK_02->value => 'asset',
                    default => 'platform'
                }
            ];
        });


        /*
    |--------------------------------------------------------------------------
    | 5️⃣ STATUS TABLE SUMMARY (SELLER / BUYER / DELIVERY)
    |--------------------------------------------------------------------------
    */
        $grouped = Account::query()
            ->selectRaw("
            owner_type,
            {$statusCase} as status,
            SUM(available_balance) as total_available_balance,
            SUM(hold_balance) as total_hold_balance,
            SUM(available_balance + hold_balance) as total_balance
        ")
            ->groupBy('owner_type', DB::raw($statusCase))
            ->get();

        $sellerSummary   = $grouped->where('owner_type', AccountOwnerTypeEnum::SELLER->value)->values();
        $buyerSummary    = $grouped->where('owner_type', AccountOwnerTypeEnum::BUYER->value)->values();
        $deliverySummary = $grouped->where('owner_type', AccountOwnerTypeEnum::DELIVERY->value)->values();


        /*
    |--------------------------------------------------------------------------
    | 6️⃣ PAYABLE / RECEIVABLE TOTALS
    |--------------------------------------------------------------------------
    */
        $balances = Account::query()
            ->selectRaw("
            owner_type,
            SUM(CASE WHEN (available_balance + hold_balance) > 0
                THEN (available_balance + hold_balance) ELSE 0 END) as payable_total,
            SUM(CASE WHEN (available_balance + hold_balance) < 0
                THEN ABS(available_balance + hold_balance) ELSE 0 END) as receivable_total
        ")
            ->groupBy('owner_type')
            ->get()
            ->keyBy('owner_type');

        $sellerPayable      = $balances[AccountOwnerTypeEnum::SELLER->value]->payable_total ?? 0;
        $sellerReceivable   = $balances[AccountOwnerTypeEnum::SELLER->value]->receivable_total ?? 0;

        $deliveryPayable    = $balances[AccountOwnerTypeEnum::DELIVERY->value]->payable_total ?? 0;
        $deliveryReceivable = $balances[AccountOwnerTypeEnum::DELIVERY->value]->receivable_total ?? 0;

        $buyerPayable       = $balances[AccountOwnerTypeEnum::BUYER->value]->payable_total ?? 0;
        $buyerReceivable    = $balances[AccountOwnerTypeEnum::BUYER->value]->receivable_total ?? 0;


        /*
    |--------------------------------------------------------------------------
    | 7️⃣ PLATFORM TOTAL MAP (REAL DB VALUES — FIXED)
    |--------------------------------------------------------------------------
    */
        $platformMap = $platformAccountsDB->mapWithKeys(fn($a) => [
            $a->accnt_code => ($a->available_balance + $a->hold_balance)
        ]);

        // $platformRevenue  = $platformMap[PlatformAccountCodeEnum::PLATFORM_REVENUE->value] ?? 0;
        // $platformTax      = $platformMap[PlatformAccountCodeEnum::PLATFORM_TAX->value] ?? 0;
        $platformClearing = $platformMap[PlatformAccountCodeEnum::PLATFORM_CLEARING->value] ?? 0;

        $cash  = $platformMap[PlatformAccountCodeEnum::CASH->value] ?? 0;
        $bank1 = $platformMap[PlatformAccountCodeEnum::BANK_01->value] ?? 0;
        $bank2 = $platformMap[PlatformAccountCodeEnum::BANK_02->value] ?? 0;


        /*
    |--------------------------------------------------------------------------
    | 8️⃣ FINANCIAL SUMMARY
    |--------------------------------------------------------------------------
    */
        $financialSummary = [
            'seller_payable'   => $sellerPayable,
            'delivery_payable' => $deliveryPayable,
            'buyer_refund'     => $buyerPayable,

            'seller_receivable'   => $sellerReceivable,
            'delivery_receivable' => $deliveryReceivable,
            'buyer_receivable'    => $buyerReceivable,

            'clearing_balance' => $platformClearing,
            // 'platform_revenue' => $platformRevenue,
            // 'tax_liability'    => $platformTax,

            'cash_balance' => $cash,
            'bank_balance' => ($bank1 + $bank2),

            'net_platform_position' => ($cash + $bank1 + $bank2)
                // - ($sellerPayable + $deliveryPayable + $platformTax)
                - ($sellerPayable + $deliveryPayable)
                + ($sellerReceivable + $deliveryReceivable + $buyerReceivable),
        ];


        /*
    |--------------------------------------------------------------------------
    | 9️⃣ FINAL RESPONSE
    |--------------------------------------------------------------------------
    */
        $summary = [
            'financial_summary' => $financialSummary,
            'platform_summary'  => $platformSummary,
            'platform_accounts' => $platformAccounts->values(),
            'seller_accounts'   => $sellerSummary,
            'buyer_accounts'    => $buyerSummary,
            'delivery_accounts' => $deliverySummary,
        ];

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            $summary
        );
    }


    // public function summary(Request $request)
    // {



    //     $platformAccounts = Account::query()
    //         ->where('owner_type', AccountOwnerTypeEnum::PLATFORM->value)
    //         ->get();

    //     $sellerAccounts = Account::query()
    //         ->where('owner_type', AccountOwnerTypeEnum::SELLER->value)
    //         ->get();

    //     $buyerAccounts = Account::query()
    //         ->where('owner_type', AccountOwnerTypeEnum::BUYER->value)
    //         ->get();

    //     $deliveryAccounts = Account::query()
    //         ->where('owner_type', AccountOwnerTypeEnum::DELIVERY->value)
    //         ->get();



    //     // Platform Balances
    //     $platformSummary  = $platformAccounts->map(function ($account) {
    //         return [
    //             'accnt_code' => $account->accnt_code,
    //             'name' => $account->name,
    //             'available_balance' => $account->available_balance,
    //             'hold_balance' => $account->hold_balance,
    //             'total_balance' => $account->available_balance + $account->hold_balance,
    //         ];
    //     });

    //     // Seller Balances
    //     $sellerSummary  = [];

    //     // Firsr Group base on on avalible balance and hold balance
    //     $sellerAccounts->groupBy(function ($account) {
    //         if ($account->available_balance > 0) {
    //             return 'available';
    //         } elseif ($account->hold_balance > 0) {
    //             return 'hold';
    //         } else {
    //             return 'zero';
    //         }
    //     })->each(function ($group, $key) use (&$sellerSummary) {
    //         $totalAvailable = $group->sum('available_balance');
    //         $totalHold = $group->sum('hold_balance');
    //         $totalBalance = $totalAvailable + $totalHold;

    //         $sellerSummary[] = [
    //             'status' => $key,
    //             'total_available_balance' => $totalAvailable,
    //             'total_hold_balance' => $totalHold,
    //             'total_balance' => $totalBalance,
    //         ];
    //     });

    //     // Buyer Balances
    //     $buyerSummary  = [];
    //     $buyerAccounts->groupBy(function ($account) {
    //         if ($account->available_balance > 0) {
    //             return 'available';
    //         } elseif ($account->hold_balance > 0) {
    //             return 'hold';
    //         } else {
    //             return 'zero';
    //         }
    //     })->each(function ($group, $key) use (&$buyerSummary) {
    //         $totalAvailable = $group->sum('available_balance');
    //         $totalHold = $group->sum('hold_balance');
    //         $totalBalance = $totalAvailable + $totalHold;

    //         $buyerSummary[] = [
    //             'status' => $key,
    //             'total_available_balance' => $totalAvailable,
    //             'total_hold_balance' => $totalHold,
    //             'total_balance' => $totalBalance,
    //         ];
    //     });


    //     // Delivery Balances
    //     $deliverySummary  = [];
    //     $deliveryAccounts->groupBy(function ($account) {
    //         if ($account->available_balance > 0) {
    //             return 'available';
    //         } elseif ($account->hold_balance > 0) {
    //             return 'hold';
    //         } else {
    //             return 'zero';
    //         }
    //     })->each(function ($group, $key) use (&$deliverySummary) {
    //         $totalAvailable = $group->sum('available_balance');
    //         $totalHold = $group->sum('hold_balance');
    //         $totalBalance = $totalAvailable + $totalHold;

    //         $deliverySummary[] = [
    //             'status' => $key,
    //             'total_available_balance' => $totalAvailable,
    //             'total_hold_balance' => $totalHold,
    //             'total_balance' => $totalBalance,
    //         ];
    //     });


    //     $summary = [
    //         'platform_accounts' => $platformSummary,
    //         'seller_accounts' => $sellerSummary,
    //         'buyer_accounts' => $buyerSummary,
    //         'delivery_accounts' => $deliverySummary,
    //     ];


    //     return $this->successResponse(__('messages.success_messages.success_get'), $summary);        //

    // }


    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
            'accnt_code' => 'nullable|string',
            'owner_id'   => 'nullable|integer',
            'query' => 'nullable|string',
            'owner_type' => 'nullable|string|in:all,buyer,seller,delivery',
            'scope' => 'nullable|string|in:all,collect,pay,negative,positive,zero',
            'is_pdf_export' => 'nullable|boolean',
            'fetch_all' => 'nullable|boolean',
        ]);

        $accntCode = $request->input('accnt_code');
        $ownerId   = $request->input('owner_id');
        $queryText = trim((string) $request->input('query', ''));
        $ownerType = $request->input('owner_type', 'all');
        $scope = $request->input('scope', 'all');

        $accntQuery = Account::with('user:id,name,user_code,nickname')
            ->whereNotIn('accnt_code', PlatformAccountCodeEnum::casesAsValues())
            ->oldest();

        if ($accntCode) {
            $accntQuery->where('accnt_code', $accntCode);
        }

        if ($ownerId) {
            $accntQuery->where('owner_id', $ownerId);
        }

        if ($ownerType !== 'all') {
            $accntQuery->where('owner_type', $ownerType);
        }

        if (in_array($scope, ['collect', 'negative'], true)) {
            $accntQuery->whereRaw('CAST(COALESCE(available_balance, 0) AS DECIMAL(20,4)) <= -0.005');
        } elseif (in_array($scope, ['pay', 'positive'], true)) {
            $accntQuery->whereRaw('CAST(COALESCE(available_balance, 0) AS DECIMAL(20,4)) >= 0.005');
        } elseif ($scope === 'zero') {
            $accntQuery->whereRaw('ABS(CAST(COALESCE(available_balance, 0) AS DECIMAL(20,4))) < 0.005');
        }

        if ($scope === 'all') {
            $accntQuery->reorder()
                ->orderByRaw('ABS(CAST(COALESCE(available_balance, 0) AS DECIMAL(20,4))) ASC')
                ->orderBy('id', 'asc');
        }

        if ($queryText !== '') {
            $accntQuery->where(function ($q) use ($queryText) {
                $q->where('accnt_code', 'like', '%'.$queryText.'%')
                    ->orWhere('name', 'like', '%'.$queryText.'%')
                    ->orWhere('owner_type', 'like', '%'.$queryText.'%')
                    ->orWhereHas('user', function ($uq) use ($queryText) {
                        $uq->where('name', 'like', '%'.$queryText.'%')
                            ->orWhere('user_code', 'like', '%'.$queryText.'%')
                            ->orWhere('nickname', 'like', '%'.$queryText.'%');
                    });
            });
        }

        $userAccounts = ($request->boolean('is_pdf_export') || $request->boolean('fetch_all'))
            ? $accntQuery->get()
            : $accntQuery->limit(100)->get();

        if ($request->boolean('is_pdf_export')) {
            return $this->successResponse(
                __('messages.success_messages.success_get'),
                $this->pdf($userAccounts)
            );
        }

        $platformAccounts = Account::oldest()->with('user:id,name,user_code,nickname')
            ->whereIn('accnt_code', PlatformAccountCodeEnum::casesAsValues())
            ->get();

        $accounts = [
            'platform_accounts' => $platformAccounts,
            'user_accounts'     => $userAccounts,
        ];

        return $this->successResponse(__('messages.success_messages.success_get'), $accounts);
    }

    protected function pdf($accounts)
    {
        $pdf = Pdf::loadView('pdf.reports.accounting.account_balance_report', [
            'generatedAt' => now(),
            'accounts' => $accounts,
        ]);

        return storeFileWithSignedUrl($pdf->output());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'name' => 'required|string|max:255',
            'owner_type' => 'required|string|max:255',
            'owner_id' => 'nullable|integer',
            'currency' => 'required|string|max:3',
            'type' => 'required|string|max:255',
        ]);


        $account =   Account::create($request->all());

        //log activity

        /// Log activity
        logActivity(
            'account_created',
            $request->user(),       // ACTOR (who did it)
            get_class($account),       // SUBJECT TYPE (what was affected)
            $account->id,              // SUBJECT ID
            $account->accnt_code,       // SUBJECT CODE (human readable)
            [
                'accnt_code' => $account->accnt_code,
                'owner_type' => $account->owner_type,
                'owner_id' => $account->owner_id,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_create'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Account $account)
    {
        //

        $account = Account::with(['ledgers', 'user:id,name,user_code,nickname'])
            ->findOrFail($account->id);


        return $this->successResponse(__('messages.success_messages.success_get'), $account);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Account $account)
    {
        //      

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            // 'owner_type' => 'sometimes|string|max:255',
            // 'owner_id' => 'sometimes|nullable|integer',
            'currency' => 'sometimes|string|max:3',
            'type' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'inactive_reason' => 'sometimes|nullable|string|max:1000',
            'credit_limit' => 'sometimes|numeric',
            'is_credit_enabled' => 'sometimes|boolean',

        ]);

        $account->update($validated);

        /// Log activity
        logActivity(
            'account_updated',
            $request->user(),       // ACTOR (who did it)
            get_class($account),       // SUBJECT TYPE (what was affected)
            $account->id,              // SUBJECT ID
            $account->accnt_code,       // SUBJECT CODE (human readable)
            [
                'accnt_code' => $account->accnt_code,
                'owner_type' => $account->owner_type,
                'owner_id' => $account->owner_id,
                'is_active' => $account->is_active,
                'inactive_reason' => $account->inactive_reason,
                'credit_limit' => $account->credit_limit,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Account $account)
    {
        //
        // Check if ledgers has data and the accnt codes from AccntCodeEnum::casesAsValues()
        if (
            $account->ledgers()->exists()
            || in_array($account->accnt_code, PlatformAccountCodeEnum::casesAsValues())
        ) {
            return $this->errorResponse(__('messages.error_messages.user_detlete_prohibited'), 403);
        }

        /// Log activity
        logActivity(
            'account_deleted',
            request()->user(),       // ACTOR (who did it)
            get_class($account),       // SUBJECT TYPE (what was affected)
            $account->id,              // SUBJECT ID
            $account->accnt_code,       // SUBJECT CODE (human readable)
            [
                'accnt_code' => $account->accnt_code,
                'owner_type' => $account->owner_type,
                'owner_id' => $account->owner_id,
            ]
        );

        $account->delete();


        return $this->showSuccessMessage(__('messages.success_messages.success_delete'));
    }
}
