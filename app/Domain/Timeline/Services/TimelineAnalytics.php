<?php

namespace App\Domain\Timeline\Services;

use App\Domain\Timeline\Queries\StepIssueStatsQuery;
use Illuminate\Support\Facades\Cache;

class TimelineAnalytics
{
    public function __construct(private StepIssueStatsQuery $q) {}

    /**
     * @param bool $force  true => bypass cache
     */
    public function frequentIssues(int $layananId, int $days = 30, int $limit = 5, bool $force = false): array
    {
        $days  = $days > 0 ? $days : 30;
        $limit = max(1, $limit);

        $key = "analytics:freq_issues:layanan:$layananId:days:$days:limit:$limit";

        $raw = $force
            ? $this->q->topIssuesPerStep($layananId, $days, $limit)
            : Cache::remember($key, now()->addMinutes(10), fn () => $this->q->topIssuesPerStep($layananId, $days, $limit));

        // Normalisasi
        $norm = [];
        foreach ($raw as $stepId => $items) {
            $stepId = (int)$stepId;
            $norm[$stepId] = array_map(fn($it) => [
                'issue_id'   => (int)($it['issue_id'] ?? 0),
                'freq'       => (int)($it['freq'] ?? 0),
                'median_sec' => (int)($it['median_sec'] ?? 0),
            ], $items);
        }
        return $norm;
    }
}
