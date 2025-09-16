<?php

namespace App\Domain\Timeline\Queries;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StepIssueStatsQuery
{
    /**
     * Ambil top issues per step untuk 1 layanan (last $days, limit per step).
     * Output:
     * [
     *   step_id => [
     *      ['issue_id'=>int, 'freq'=>int], ...
     *   ],
     *   ...
     * ]
     */
    public function topIssuesPerStep(int $layananId, int $days = 30, int $limit = 3): array
    {
        $since = Carbon::now()->subDays(max(1, $days));

        // Skema yang dipakai:
        // - progress            (p)
        // - progress_issue      (pi)  [opsional; kalau ada pivot]
        // - tickets             (t)   [untuk filter layanan_id]
        // - k_steps             (s)   [relasi step]
        //
        // Kita dukung 2 sumber issue: pi.issue_id (pivot) ATAU p.issue_id (kolom langsung)
        // -> COALESCE(pi.issue_id, p.issue_id)
        $rows = DB::table('progress as p')
            ->join('tickets as t', 't.id', '=', 'p.ticket_id')
            ->join('k_steps as s', 's.id', '=', 'p.step_id')
            ->leftJoin('progress_issue as pi', 'pi.progress_id', '=', 'p.id')
            ->where('t.layanan_id', $layananId)
            ->whereNotNull('p.started_at')
            ->whereNotNull('p.ended_at')
            ->where('p.ended_at', '>=', $since)
            ->where(function ($q) {
                $q->whereNotNull('pi.issue_id')->orWhereNotNull('p.issue_id');
            })
            ->selectRaw('p.step_id, COALESCE(pi.issue_id, p.issue_id) as issue_id, COUNT(*) as freq')
            ->groupBy('p.step_id', DB::raw('COALESCE(pi.issue_id, p.issue_id)'))
            ->orderBy('p.step_id')
            ->orderByDesc('freq')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // Bucket per step + ambil top $limit
        $result = [];
        foreach ($rows as $r) {
            $stepId  = (int) $r->step_id;
            $issueId = (int) $r->issue_id;
            $freq    = (int) $r->freq;

            $result[$stepId] ??= [];
            $result[$stepId][] = ['issue_id' => $issueId, 'freq' => $freq];
        }

        foreach ($result as $stepId => $items) {
            // sudah diorder desc freq oleh query; cukup slice
            $result[$stepId] = array_slice($items, 0, max(1, $limit));
        }

        return $result;
    }
}
