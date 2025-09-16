<?php

namespace App\Services;

use App\Models\KIssue;
use App\Support\Nlp;

class KIssueMatcher
{
    public static function exact(string $q): ?KIssue
    {
        $normQ    = KIssue::normalizePhrase($q);
        $qTokens  = Nlp::tokens($q);
        $qTokensU = array_values(array_unique($qTokens)); sort($qTokensU);
        if (empty($qTokensU)) return null;

        // A) exact phrase/alias
        $byNorm = KIssue::query()
            ->where('issue_name_norm', $normQ)
            ->orWhereJsonContains('aliases_norm', $normQ)
            ->orderBy('issue_name_norm')
            ->orderByDesc('updated_at')
            ->orderBy('id')
            ->first();
        if ($byNorm) return $byNorm;

        // B) kandidat harus mengandung SEMUA anchor informatif
        $anchors = self::pickAnchors($qTokensU);
        $pool = KIssue::query()
            ->where(function($w) use ($anchors) {
                foreach ($anchors as $a) $w->whereJsonContains('issue_tokens', $a);
            })
            ->select('id','issue_name','solusion','issue_tokens','issue_name_norm','updated_at')
            ->limit(50)->get();

        if ($pool->isEmpty()) return null;

        // C) set-of-tokens sama persis
        foreach ($pool as $r) {
            $t = is_array($r->issue_tokens) ? $r->issue_tokens : (json_decode($r->issue_tokens, true) ?: []);
            $t = array_values(array_unique($t)); sort($t);
            if ($t === $qTokensU) return $r;
        }

        // D) anchor-subset → pilih terbaik (spesifik, terbaru, id terkecil)
        return $pool->sort(function($a, $b) use ($qTokensU) {
            $ta = is_array($a->issue_tokens) ? $a->issue_tokens : (json_decode($a->issue_tokens, true) ?: []);
            $tb = is_array($b->issue_tokens) ? $b->issue_tokens : (json_decode($b->issue_tokens, true) ?: []);
            $covA = self::covers($ta, $qTokensU);
            $covB = self::covers($tb, $qTokensU);
            if ($covA !== $covB) return $covA ? -1 : 1;
            $lenA = strlen($a->issue_name_norm ?? '');
            $lenB = strlen($b->issue_name_norm ?? '');
            if ($lenA !== $lenB) return $lenA <=> $lenB;
            $dt = strcmp((string)$b->updated_at, (string)$a->updated_at);
            if ($dt !== 0) return $dt;
            return ($a->id <=> $b->id);
        })->first();
    }

    protected static function covers(array $docTokens, array $qTokensU): bool
    {
        $doc = array_flip($docTokens);
        foreach ($qTokensU as $t) if (!isset($doc[$t])) return false;
        return true;
    }

    protected static function pickAnchors(array $qTokensU): array
    {
        $df = Nlp::df();
        $bag = [];
        foreach ($qTokensU as $t) $bag[] = ['t'=>$t, 'df'=>$df[$t] ?? PHP_INT_MAX];
        usort($bag, fn($a,$b)=>($a['df'] <=> $b['df']));
        return array_map(fn($x)=>$x['t'], array_slice($bag, 0, min(3, count($bag))));
    }
}
