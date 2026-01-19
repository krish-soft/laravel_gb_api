<?php

namespace App\Http\Controllers\Web\Webhooks;

use App\Models\Common\Payment\Payout;
use App\Services\Common\Payment\Handlers\PayoutHandler;
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

        $payout = Payout::where(
            'razorpay_payout_id',
            $razorpayPayoutId
        )->first();

        if (!$payout) {
            return response()->json(['ok' => true]);
        }

        $handler = app(PayoutHandler::class);

        if ($event === 'payout.processed') {
            $handler->onSuccess($payout, $razorpayPayoutId);
        }

        if ($event === 'payout.failed') {
            $handler->onFailure($payout, 'Razorpay payout failed');
        }

        return response()->json(['ok' => true]);
    }
}
