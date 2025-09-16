<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Domain\Timeline\Services\TimelineAnalytics;

class AnalyticsController extends Controller
{
    public function frequentIssues(Request $req, TimelineAnalytics $svc)
    {
        $req->validate([
            'layanan_id' => 'required|integer',
            'days'       => 'nullable|integer|min:1|max:365',
            'limit'      => 'nullable|integer|min:1|max:20',
        ]);

        $layananId = (int)$req->input('layanan_id');
        $days      = (int)$req->input('days', 30);
        $limit     = (int)$req->input('limit', 5);

        $grouped = $svc->frequentIssues($layananId, $days, $limit);

        $issueIds = collect($grouped)->flatten(1)->pluck('issue_id')->unique()->values();
        $names = $issueIds->isNotEmpty()
            ? DB::table('k_issues')->whereIn('id', $issueIds)->pluck('issue_name','id')
            : collect();

        $payload = [];
        foreach ($grouped as $stepId => $items) {
            $payload[$stepId] = array_map(function($it) use ($names) {
                return [
                    'issue_id'   => $it['issue_id'],
                    'issue_name' => $names[$it['issue_id']] ?? ("Issue #".$it['issue_id']),
                    'freq'       => $it['freq'],
                    'median_sec' => $it['median_sec'],
                ];
            }, $items);
        }

        return response()->json([
            'layanan_id' => $layananId,
            'days'       => $days,
            'limit'      => $limit,
            'data'       => $payload,
            'server_now' => now()->toIso8601String(),
        ]);
    }
}
