<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\Log;

use App\Http\Controllers\Controller;
use App\Models\Common\Log\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Activity Logs List
     */
    public function index(Request $request): JsonResponse
    {
        $logs = ActivityLog::latest('created_at')->get();
        $logs = $logs->map(function ($log) {

            return [
                'id' => $log->id,
                'event' => $log->event,

                // ACTOR
                'user_id' => $log->actor_id,
                'user_code' => $log->actor_code,
                'user_name' => $log->actor_snapshot['name'] ?? null,
                'user_email' => $log->actor_snapshot['email'] ?? null,
                'user_phone_number' => $log->actor_snapshot['phone_number'] ?? null,
                // 'user_role' => $log->actor_snapshot['role'] ?? null,
                'user_type' => $log->actor_snapshot['user_type'] ?? null,


                // META (important)
                'addr_code' => $log->meta['addr_code'] ?? null,
                'bill_addr_code' => $log->meta['bill_addr_code'] ?? null,

                // SYSTEM
                // 'ip_address' => $log->ip_address,
                // LAST LOGIN 🔥
                'last_login_at' => $log->actor->last_login_at ?? null,
                'last_login_ip' => $log->actor->last_login_ip ?? null,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Activity logs fetched successfully',
            'data' => $logs,
        ]);
    }
}
