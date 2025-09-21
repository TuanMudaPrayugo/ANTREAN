<?php

namespace App\Services;

use App\Models\KIssue;
use App\Support\Nlp;
use Illuminate\Support\Str;

/**
 * Pencocokan cepat & presisi ke tabel kissues.
 *
 * Urutan strategi:
 *  A) Exact phrase/alias   -> paling presisi & murah
 *  B) Anchor subset (issue_tokens mengandung 2–3 token paling informatif)
 *  C) Token-set equality   -> jika sama persis dengan query
 *  D) Ranking anchored     -> coverage + jaccard + recency + judul spesifik
 *  E) Fuzzy fallback       -> tahan typo/singkatan (Jaro/Levenshtein via similar_text)
 *
 * Return KIssue|null (NULL kalau tidak ada kandidat yang cukup layak).
 */
class KIssueMatcher
{
    // Ambang untuk fallback fuzzy (0–100 dari similar_text)
    private const FUZZY_MIN_PCT       = 85.0;
    // Batas pool untuk fuzzy agar tetap ringan
    private const FUZZY_POOL_SIZE     = 200;
    // Batas awal kandidat untuk anchor-subset
    private const ANCHOR_POOL_LIMIT   = 80;

    public static function exact(string $q): ?KIssue
    {
        $normQ    = KIssue::normalizePhrase($q);     // "stnk hilang bagaimana"
        $qTokens  = Nlp::tokens($q);                 // ['stnk','hilang','bagaimana']
        $qTokensU = array_values(array_unique($qTokens));
        sort($qTokensU);

        if (empty($qTokensU)) {
            return null;
        }

        /* ---------- A) Exact phrase/alias ---------- */
        $byNorm = KIssue::query()
            ->where('issue_name_norm', $normQ)
            ->orWhereJsonContains('aliases_norm', $normQ)
            ->orderBy('issue_name_norm')
            ->orderByDesc('updated_at')
            ->orderBy('id')
            ->first();

        if ($byNorm) return $byNorm;

        /* ---------- B) Anchor-subset (mengandung semua anchor) ---------- */
        $anchors = self::pickAnchors($qTokensU); // 2–3 token paling informatif
        $pool = KIssue::query()
            ->where(function ($w) use ($anchors) {
                foreach ($anchors as $a) {
                    $w->whereJsonContains('issue_tokens', $a);
                }
            })
            ->select('id','issue_name','solusion','issue_tokens','issue_name_norm','updated_at')
            ->limit(self::ANCHOR_POOL_LIMIT)
            ->get();

        if ($pool->isEmpty()) {
            // Tidak ada kandidat anchor → langsung coba fuzzy
            return self::fuzzyFallback($normQ);
        }

        /* ---------- C) Token-set equality ---------- */
        foreach ($pool as $r) {
            $t = self::asTokens($r->issue_tokens);
            if ($t === $qTokensU) {
                return $r;
            }
        }

        /* ---------- D) Ranking anchored ---------- */
        $ranked = $pool->sort(function ($a, $b) use ($qTokensU) {
            $ta = self::asTokens($a->issue_tokens);
            $tb = self::asTokens($b->issue_tokens);

            // 1) coverage penuh (semua token query ada)
            $covA = self::covers($ta, $qTokensU);
            $covB = self::covers($tb, $qTokensU);
            if ($covA !== $covB) return $covA ? -1 : 1;

            // 2) jaccard lebih besar
            $ja = self::jaccard($ta, $qTokensU);
            $jb = self::jaccard($tb, $qTokensU);
            if ($ja !== $jb) return ($jb <=> $ja);

            // 3) judul lebih pendek = lebih spesifik
            $la = strlen((string)$a->issue_name_norm);
            $lb = strlen((string)$b->issue_name_norm);
            if ($la !== $lb) return $la <=> $lb;

            // 4) terbaru
            $dt = strcmp((string)$b->updated_at, (string)$a->updated_at);
            if ($dt !== 0) return $dt;

            // 5) id lebih kecil
            return ($a->id <=> $b->id);
        })->values();

        /** kandidat terbaik dari ranking anchored */
        $best = $ranked->first();
        if ($best) {
            return $best;
        }

        /* ---------- E) Fuzzy fallback (typo/singkatan) ---------- */
        return self::fuzzyFallback($normQ);
    }

    /* ======================= Helpers ======================= */

    /** Ubah field issue_tokens (json/array) ke array token terurut unik */
    private static function asTokens($val): array
    {
        $t = is_array($val) ? $val : (json_decode($val, true) ?: []);
        $t = array_values(array_unique(array_map('strval', $t)));
        sort($t);
        return $t;
    }

    /** Semua token query tercakup di dokumen? */
    private static function covers(array $docTokens, array $qTokensU): bool
    {
        $doc = array_flip($docTokens);
        foreach ($qTokensU as $t) if (!isset($doc[$t])) return false;
        return true;
    }

    /** Ambil 2–3 token paling informatif (df paling kecil) */
    private static function pickAnchors(array $qTokensU): array
    {
        $df = Nlp::df();
        $bag = [];
        foreach ($qTokensU as $t) {
            $bag[] = ['t' => $t, 'df' => $df[$t] ?? PHP_INT_MAX];
        }
        usort($bag, fn($a,$b) => ($a['df'] <=> $b['df']));
        $anchors = array_map(fn($x) => $x['t'], array_slice($bag, 0, min(3, count($bag))));
        // Minimal 1 anchor tetap dipakai
        return !empty($anchors) ? $anchors : $qTokensU;
    }

    /** Jaccard similarity antara dua set token */
    private static function jaccard(array $a, array $b): float
    {
        $A = array_flip($a); $B = array_flip($b);
        $inter = 0;
        foreach ($A as $t => $_) if (isset($B[$t])) $inter++;
        $union = count($A) + count($B) - $inter;
        return $union > 0 ? $inter / $union : 0.0;
    }

    /**
     * Fuzzy fallback:
     * - Bandingkan query (norm) dengan issue_name_norm & aliases_norm
     * - Pakai similar_text (mendekati Jaro/Levenshtein) → persen 0..100
     * - Ambang default 85% (aman untuk typo ringan/singkatan)
     */
    private static function fuzzyFallback(string $normQ): ?KIssue
    {
        // Ambil pool terbatas yang paling mungkin relevan:
        //  - FT LIKE sederhana berdasarkan 1–2 kata dari query
        //  - kalau gagal, ambil terbaru terbatas
        $words = array_values(array_filter(
            explode(' ', $normQ),
            fn($w) => mb_strlen($w) >= 3
        ));
        $poolQuery = KIssue::query()->select('id','issue_name','issue_name_norm','aliases_norm','solusion','updated_at');

        if (!empty($words)) {
            $poolQuery->where(function($q) use ($words) {
                foreach (array_slice($words, 0, 2) as $w) {
                    $q->orWhere('issue_name_norm', 'like', "%{$w}%")
                      ->orWhereJsonContains('aliases_norm', $w);
                }
            });
        }

        $pool = $poolQuery
            ->orderByDesc('updated_at')
            ->limit(self::FUZZY_POOL_SIZE)
            ->get();

        if ($pool->isEmpty()) {
            $pool = KIssue::query()
                ->select('id','issue_name','issue_name_norm','aliases_norm','solusion','updated_at')
                ->orderByDesc('updated_at')
                ->limit(self::FUZZY_POOL_SIZE)
                ->get();
        }

        $best = null; $bestPct = 0.0;

        foreach ($pool as $row) {
            $cands = [ (string)$row->issue_name_norm ];
            $aliases = is_array($row->aliases_norm) ? $row->aliases_norm : (json_decode($row->aliases_norm ?? '[]', true) ?: []);
            foreach ($aliases as $al) $cands[] = (string)$al;

            foreach ($cands as $cand) {
                $cand = KIssue::normalizePhrase($cand);
                if ($cand === '') continue;

                // similar_text menghasilkan persen 0..100
                similar_text($normQ, $cand, $pct);

                // Bonus kecil bila salah satu mengandung yang lain (substring)
                if (str_contains($cand, $normQ) || str_contains($normQ, $cand)) {
                    $pct = max($pct, 90.0);
                }

                if ($pct > $bestPct) {
                    $bestPct = $pct;
                    $best    = $row;
                }
            }
        }

        return ($best && $bestPct >= self::FUZZY_MIN_PCT) ? $best : null;
    }
}
