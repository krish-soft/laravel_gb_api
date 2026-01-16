<?php

namespace App\Http\Controllers\Web\Webhooks;

use App\Models\Common\Wallet\WalletPayout;
use App\Services\Common\Payment\Handlers\WalletPayoutHandler;
use Illuminate\Http\Request;

class RazorpayPayoutWebhookController
{
    public function handle(Request $request)
    {
        $event = $request->input('event');

        $razorpayPayoutId = data_get(
            $request->all(),
            'payload.payout.entity.id'
        );

        if (!$razorpayPayoutId) {
            return response()->json(['ok' => true]);
        }

        $payout = WalletPayout::where(
            'razorpay_payout_id',
            $razorpayPayoutId
        )->first();

        if (!$payout) {
            return response()->json(['ok' => true]);
        }

        $handler = app(WalletPayoutHandler::class);

        if ($event === 'payout.processed') {
            $handler->onSuccess($payout, $razorpayPayoutId);
        }

        if ($event === 'payout.failed') {
            $handler->onFailure($payout, 'Razorpay payout failed');
        }

        return response()->json(['ok' => true]);
    }
}
