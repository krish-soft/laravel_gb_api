<?php

namespace App\Http\Controllers\Api\v1\Admin\Report\Log;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Common\Log\ActivityLog;
use App\Models\Common\Log\AuditLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminLogApiController extends ApiResponseWithAdminAuthController
{
    public function getAuditLogs(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'search' => 'nullable|string',
            'user_code' => 'nullable|string',
            'action' => 'nullable|string',
            'ip_address' => 'nullable|string',
            'is_export' => 'nullable|boolean',
        ]);

        [$start, $end] = $this->resolveDateRange($request);

        $query = AuditLog::query()
            ->whereBetween('created_at', [$start, $end]);

        if ($request->filled('user_code')) {
            $query->where('user_code', 'like', '%'.$request->user_code.'%');
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.$request->action.'%');
        }

        if ($request->filled('ip_address')) {
            $query->where('ip_address', 'like', '%'.$request->ip_address.'%');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('user_code', 'like', '%'.$search.'%')
                    ->orWhere('auditable_type', 'like', '%'.$search.'%')
                    ->orWhere('auditable_id', 'like', '%'.$search.'%')
                    ->orWhere('action', 'like', '%'.$search.'%')
                    ->orWhere('ip_address', 'like', '%'.$search.'%')
                    ->orWhere('reason', 'like', '%'.$search.'%');
            });
        }

        $query->orderByDesc('id');

        if ($request->boolean('is_export')) {
            $rows = $query->limit(5000)->get();

            return $this->successResponse(
                __('messages.success_messages.success_get'),
                $this->exportAuditCsv($rows)
            );
        }

        $rows = $query->limit(2000)->get();

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'rows' => $rows,
            ]
        );
    }

    public function getActivityLogs(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'search' => 'nullable|string',
            'actor_code' => 'nullable|string',
            'event' => 'nullable|string',
            'ip_address' => 'nullable|string',
            'is_export' => 'nullable|boolean',
        ]);

        [$start, $end] = $this->resolveDateRange($request);

        $query = ActivityLog::query()
            ->whereBetween('created_at', [$start, $end]);

        if ($request->filled('actor_code')) {
            $query->where('actor_code', 'like', '%'.$request->actor_code.'%');
        }

        if ($request->filled('event')) {
            $query->where('event', 'like', '%'.$request->event.'%');
        }

        if ($request->filled('ip_address')) {
            $query->where('ip_address', 'like', '%'.$request->ip_address.'%');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('event', 'like', '%'.$search.'%')
                    ->orWhere('actor_code', 'like', '%'.$search.'%')
                    ->orWhere('subject_code', 'like', '%'.$search.'%')
                    ->orWhere('subject_type', 'like', '%'.$search.'%')
                    ->orWhere('ip_address', 'like', '%'.$search.'%');
            });
        }

        $query->orderByDesc('id');

        if ($request->boolean('is_export')) {
            $rows = $query->limit(5000)->get();

            return $this->successResponse(
                __('messages.success_messages.success_get'),
                $this->exportActivityCsv($rows)
            );
        }

        $rows = $query->limit(2000)->get();

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'rows' => $rows,
            ]
        );
    }

    public function getLogsSummary(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'log_type' => 'nullable|string|in:all,audit,activity',
        ]);

        [$start, $end] = $this->resolveDateRange($request);
        $logType = (string) ($request->input('log_type') ?: 'all');

        $auditQuery = AuditLog::query()->whereBetween('created_at', [$start, $end]);
        $activityQuery = ActivityLog::query()->whereBetween('created_at', [$start, $end]);

        $auditCount = in_array($logType, ['all', 'audit'], true) ? (clone $auditQuery)->count() : 0;
        $activityCount = in_array($logType, ['all', 'activity'], true) ? (clone $activityQuery)->count() : 0;

        $suspiciousAuditCount = in_array($logType, ['all', 'audit'], true)
            ? (clone $auditQuery)
                ->where(function ($q) {
                    $q->where('action', 'like', '%fail%')
                        ->orWhere('action', 'like', '%reject%')
                        ->orWhere('action', 'like', '%delete%')
                        ->orWhere('reason', 'like', '%unauthor%')
                        ->orWhere('reason', 'like', '%fraud%');
                })->count()
            : 0;

        $suspiciousActivityCount = in_array($logType, ['all', 'activity'], true)
            ? (clone $activityQuery)
                ->where(function ($q) {
                    $q->where('event', 'like', '%fail%')
                        ->orWhere('event', 'like', '%reject%')
                        ->orWhere('event', 'like', '%unauthor%')
                        ->orWhere('event', 'like', '%password%')
                        ->orWhere('event', 'like', '%reset%');
                })->count()
            : 0;

        $topAuditActions = in_array($logType, ['all', 'audit'], true)
            ? (clone $auditQuery)
                ->selectRaw('action, COUNT(*) as total')
                ->groupBy('action')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
            : collect();

        $topActivityEvents = in_array($logType, ['all', 'activity'], true)
            ? (clone $activityQuery)
                ->selectRaw('event, COUNT(*) as total')
                ->groupBy('event')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
            : collect();

        $topIpAddresses = collect();
        if (in_array($logType, ['all', 'audit'], true)) {
            $topIpAddresses = $topIpAddresses->merge(
                (clone $auditQuery)
                    ->selectRaw('ip_address, COUNT(*) as total')
                    ->whereNotNull('ip_address')
                    ->groupBy('ip_address')
                    ->orderByDesc('total')
                    ->limit(5)
                    ->get()
            );
        }

        if (in_array($logType, ['all', 'activity'], true)) {
            $topIpAddresses = $topIpAddresses->merge(
                (clone $activityQuery)
                    ->selectRaw('ip_address, COUNT(*) as total')
                    ->whereNotNull('ip_address')
                    ->groupBy('ip_address')
                    ->orderByDesc('total')
                    ->limit(5)
                    ->get()
            );
        }

        $topIpAddresses = $topIpAddresses
            ->groupBy('ip_address')
            ->map(function ($rows, $ip) {
                return [
                    'ip_address' => $ip,
                    'total' => $rows->sum('total'),
                ];
            })
            ->sortByDesc('total')
            ->take(5)
            ->values();

        $recentAudit = in_array($logType, ['all', 'audit'], true)
            ? (clone $auditQuery)
                ->orderByDesc('created_at')
                ->limit(1500)
                ->get(['id', 'user_code', 'action', 'reason', 'ip_address', 'created_at'])
            : collect();

        $recentActivity = in_array($logType, ['all', 'activity'], true)
            ? (clone $activityQuery)
                ->orderByDesc('created_at')
                ->limit(1500)
                ->get(['id', 'actor_code', 'event', 'ip_address', 'created_at'])
            : collect();

        $highRiskUsers = collect();

        foreach ($recentAudit as $row) {
            if (! $this->isSuspiciousAuditRow($row)) {
                continue;
            }

            $key = trim((string) ($row->user_code ?: 'unknown'));
            $highRiskUsers[$key] = ($highRiskUsers[$key] ?? 0) + 1;
        }

        foreach ($recentActivity as $row) {
            if (! $this->isSuspiciousActivityRow($row)) {
                continue;
            }

            $key = trim((string) ($row->actor_code ?: 'unknown'));
            $highRiskUsers[$key] = ($highRiskUsers[$key] ?? 0) + 1;
        }

        $highRiskUsers = collect($highRiskUsers)
            ->map(function ($count, $userCode) {
                return [
                    'user_code' => $userCode,
                    'total' => $count,
                ];
            })
            ->sortByDesc('total')
            ->take(10)
            ->values();

        $highRiskIps = collect();

        foreach ($recentAudit as $row) {
            if (! $this->isSuspiciousAuditRow($row)) {
                continue;
            }

            $key = trim((string) ($row->ip_address ?: 'unknown'));
            $highRiskIps[$key] = ($highRiskIps[$key] ?? 0) + 1;
        }

        foreach ($recentActivity as $row) {
            if (! $this->isSuspiciousActivityRow($row)) {
                continue;
            }

            $key = trim((string) ($row->ip_address ?: 'unknown'));
            $highRiskIps[$key] = ($highRiskIps[$key] ?? 0) + 1;
        }

        $highRiskIps = collect($highRiskIps)
            ->map(function ($count, $ipAddress) {
                return [
                    'ip_address' => $ipAddress,
                    'total' => $count,
                ];
            })
            ->sortByDesc('total')
            ->take(10)
            ->values();

        $recentAlerts = collect();

        foreach ($recentAudit as $row) {
            if (! $this->isSuspiciousAuditRow($row)) {
                continue;
            }

            $recentAlerts->push([
                'source' => 'audit',
                'log_id' => $row->id,
                'actor_code' => $row->user_code ?: 'unknown',
                'ip_address' => $row->ip_address ?: 'unknown',
                'message' => trim(($row->action ?: 'action').($row->reason ? ' - '.$row->reason : '')),
                'created_at' => optional($row->created_at)->toDateTimeString(),
            ]);
        }

        foreach ($recentActivity as $row) {
            if (! $this->isSuspiciousActivityRow($row)) {
                continue;
            }

            $recentAlerts->push([
                'source' => 'activity',
                'log_id' => $row->id,
                'actor_code' => $row->actor_code ?: 'unknown',
                'ip_address' => $row->ip_address ?: 'unknown',
                'message' => $row->event ?: 'event',
                'created_at' => optional($row->created_at)->toDateTimeString(),
            ]);
        }

        $recentAlerts = $recentAlerts
            ->sortByDesc('created_at')
            ->take(20)
            ->values();

        $now = now();
        $window15 = $now->copy()->subMinutes(15);
        $window60 = $now->copy()->subMinutes(60);

        $auditLast15 = in_array($logType, ['all', 'audit'], true)
            ? (clone $auditQuery)->where('created_at', '>=', $window15)->count()
            : 0;
        $activityLast15 = in_array($logType, ['all', 'activity'], true)
            ? (clone $activityQuery)->where('created_at', '>=', $window15)->count()
            : 0;
        $auditLast60 = in_array($logType, ['all', 'audit'], true)
            ? (clone $auditQuery)->where('created_at', '>=', $window60)->count()
            : 0;
        $activityLast60 = in_array($logType, ['all', 'activity'], true)
            ? (clone $activityQuery)->where('created_at', '>=', $window60)->count()
            : 0;

        $auditSuspiciousLast15 = in_array($logType, ['all', 'audit'], true)
            ? (clone $auditQuery)
                ->where('created_at', '>=', $window15)
                ->where(function ($q) {
                    $q->where('action', 'like', '%fail%')
                        ->orWhere('action', 'like', '%reject%')
                        ->orWhere('action', 'like', '%delete%')
                        ->orWhere('reason', 'like', '%unauthor%')
                        ->orWhere('reason', 'like', '%fraud%')
                        ->orWhere('reason', 'like', '%denied%');
                })->count()
            : 0;

        $activitySuspiciousLast15 = in_array($logType, ['all', 'activity'], true)
            ? (clone $activityQuery)
                ->where('created_at', '>=', $window15)
                ->where(function ($q) {
                    $q->where('event', 'like', '%fail%')
                        ->orWhere('event', 'like', '%reject%')
                        ->orWhere('event', 'like', '%unauthor%')
                        ->orWhere('event', 'like', '%password%')
                        ->orWhere('event', 'like', '%reset%')
                        ->orWhere('event', 'like', '%otp%')
                        ->orWhere('event', 'like', '%blocked%');
                })->count()
            : 0;

        $unknownActorEvents = in_array($logType, ['all', 'activity'], true)
            ? (clone $activityQuery)
                ->where(function ($q) {
                    $q->whereNull('actor_code')
                        ->orWhere('actor_code', '');
                })->count()
            : 0;

        $timeline = $this->buildTimeline($recentAudit, $recentActivity, 12, 5);

        $totalEvents = $auditCount + $activityCount;
        $suspiciousTotal = $suspiciousAuditCount + $suspiciousActivityCount;
        $suspiciousRatio = $totalEvents > 0 ? round(($suspiciousTotal / $totalEvents) * 100, 2) : 0;

        $alertLevel = 'low';
        if ($suspiciousRatio >= 20 || ($auditSuspiciousLast15 + $activitySuspiciousLast15) >= 20) {
            $alertLevel = 'critical';
        } elseif ($suspiciousRatio >= 10 || ($auditSuspiciousLast15 + $activitySuspiciousLast15) >= 10) {
            $alertLevel = 'high';
        } elseif ($suspiciousRatio >= 5 || ($auditSuspiciousLast15 + $activitySuspiciousLast15) >= 5) {
            $alertLevel = 'medium';
        }

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'audit_count' => $auditCount,
                'activity_count' => $activityCount,
                'suspicious_count' => $suspiciousAuditCount + $suspiciousActivityCount,
                'suspicious_ratio' => $suspiciousRatio,
                'alert_level' => $alertLevel,
                'top_audit_actions' => $topAuditActions,
                'top_activity_events' => $topActivityEvents,
                'top_ip_addresses' => $topIpAddresses,
                'high_risk_users' => $highRiskUsers,
                'high_risk_ips' => $highRiskIps,
                'recent_alerts' => $recentAlerts,
                'timeline' => $timeline,
                'last_15m_total' => $auditLast15 + $activityLast15,
                'last_60m_total' => $auditLast60 + $activityLast60,
                'last_15m_suspicious' => $auditSuspiciousLast15 + $activitySuspiciousLast15,
                'unknown_actor_events' => $unknownActorEvents,
            ]
        );
    }

    private function isSuspiciousAuditRow($row): bool
    {
        $action = strtolower((string) ($row->action ?? ''));
        $reason = strtolower((string) ($row->reason ?? ''));

        return str_contains($action, 'fail')
            || str_contains($action, 'reject')
            || str_contains($action, 'delete')
            || str_contains($reason, 'unauthor')
            || str_contains($reason, 'fraud')
            || str_contains($reason, 'denied');
    }

    private function isSuspiciousActivityRow($row): bool
    {
        $event = strtolower((string) ($row->event ?? ''));

        return str_contains($event, 'fail')
            || str_contains($event, 'reject')
            || str_contains($event, 'unauthor')
            || str_contains($event, 'password')
            || str_contains($event, 'reset')
            || str_contains($event, 'otp')
            || str_contains($event, 'blocked');
    }

    private function buildTimeline($auditRows, $activityRows, int $steps = 12, int $minutesPerStep = 5): array
    {
        $start = now()->subMinutes($steps * $minutesPerStep);
        $buckets = [];

        for ($i = 0; $i < $steps; $i++) {
            $bucketStart = $start->copy()->addMinutes($i * $minutesPerStep);
            $key = $bucketStart->format('H:i');

            $buckets[$key] = [
                'time' => $key,
                'audit_total' => 0,
                'activity_total' => 0,
                'suspicious_total' => 0,
            ];
        }

        foreach ($auditRows as $row) {
            if (! $row->created_at) {
                continue;
            }

            $minutesFromStart = $start->diffInMinutes($row->created_at, false);
            if ($minutesFromStart < 0 || $minutesFromStart >= $steps * $minutesPerStep) {
                continue;
            }

            $index = (int) floor($minutesFromStart / $minutesPerStep);
            $timeKey = array_keys($buckets)[$index] ?? null;
            if (! $timeKey) {
                continue;
            }

            $buckets[$timeKey]['audit_total']++;
            if ($this->isSuspiciousAuditRow($row)) {
                $buckets[$timeKey]['suspicious_total']++;
            }
        }

        foreach ($activityRows as $row) {
            if (! $row->created_at) {
                continue;
            }

            $minutesFromStart = $start->diffInMinutes($row->created_at, false);
            if ($minutesFromStart < 0 || $minutesFromStart >= $steps * $minutesPerStep) {
                continue;
            }

            $index = (int) floor($minutesFromStart / $minutesPerStep);
            $timeKey = array_keys($buckets)[$index] ?? null;
            if (! $timeKey) {
                continue;
            }

            $buckets[$timeKey]['activity_total']++;
            if ($this->isSuspiciousActivityRow($row)) {
                $buckets[$timeKey]['suspicious_total']++;
            }
        }

        return array_values($buckets);
    }

    private function resolveDateRange(Request $request): array
    {
        $start = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : now()->subDay()->startOfDay();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : now()->endOfDay();

        return [$start, $end];
    }

    private function exportAuditCsv($rows): string
    {
        $headers = ['#', 'User Code', 'Action', 'Auditable Type', 'Auditable ID', 'IP Address', 'Reason', 'Created At'];

        $csv = $this->buildCsv($headers, $rows->values()->map(function ($row, $index) {
            return [
                $index + 1,
                $row->user_code,
                $row->action,
                $row->auditable_type,
                $row->auditable_id,
                $row->ip_address,
                $row->reason,
                optional($row->created_at)->toDateTimeString(),
            ];
        })->all());

        return storeFileWithSignedUrl($csv, 'temp', 'csv', 'public', 10, true);
    }

    private function exportActivityCsv($rows): string
    {
        $headers = ['#', 'Event', 'Actor Type', 'Actor Code', 'Subject Type', 'Subject ID', 'IP Address', 'Created At'];

        $csv = $this->buildCsv($headers, $rows->values()->map(function ($row, $index) {
            return [
                $index + 1,
                $row->event,
                $row->actor_type,
                $row->actor_code,
                $row->subject_type,
                $row->subject_id,
                $row->ip_address,
                optional($row->created_at)->toDateTimeString(),
            ];
        })->all());

        return storeFileWithSignedUrl($csv, 'temp', 'csv', 'public', 10, true);
    }

    private function buildCsv(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string) $csv;
    }
}
