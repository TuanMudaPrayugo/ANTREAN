<?php

namespace App\Services;

use App\Models\KIssue;
use App\Support\Nlp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Hybrid = BM25 (FULLTEXT judul) + ANN (Qdrant, embed judul) + RRF
 * Fokus pencocokan JUDUL (issue_name) → stabil & cepat.
 * Selalu mengembalikan kandidat terurut + keputusan "boleh jawab langsung".
 */
class HybridSearch
{
    /* ====== Parameter baku (tanpa .env) ====== */
    private const BM25_TOPK   = 30;
    private const ANN_TOPK    = 30;
    private const TAKE_TOP    = 5;
    private const RRF_K       = 60.0;

    // Keputusan confidence “jawab langsung”
    private const CONF_MIN    = 0.55;  // skor fused minimal
    private const CONF_GAP    = 0.08;  // selisih best - second
    private const CONF_RATIO  = 1.20;  // rasio best/second

    // Boost untuk judul yang sangat mirip / sama dengan query
    private const EXACT_BOOST = 0.25;  // dibatasi ke 1.0 setelah penambahan

    public function __construct(
        private Embedder $embedder,   // embed judul
        private QdrantClient $qdrant
    ) {}

    /**
     * @return array{0:bool,1:array,2:array} => [$ok, $best, $top]
     * - $ok   : apakah confidence memenuhi ambang (boleh jawab langsung)
     * - $best : {id,title,answer,score}
     * - $top  : kandidat fused {id,title,answer,score,bm25,ann}
     */
    public function query(string $q, int $take = self::TAKE_TOP): array
    {
        $q = trim($q);
        if ($q === '') {
            return [false, ['id'=>0,'title'=>'','answer'=>'','score'=>0.0], []];
        }

        // 1) Kandidat dari BM25 (FULLTEXT judul) + ANN (embedding judul)
        $bm  = $this->rescale($this->searchBM25($q, self::BM25_TOPK), 'score'); // 0..1
        $ann = $this->rescale($this->searchANN($q, self::ANN_TOPK),  'score'); // 0..1

        if (empty($bm) && empty($ann)) {
            // Fallback agar UI tidak kosong (tetap dianggap tidak yakin)
            $fallback = KIssue::orderByDesc('id')->limit($take)
                ->get(['id','issue_name as title','solusion as answer'])
                ->map(fn($r)=>[
                    'id'=>(int)$r->id,
                    'title'=>(string)$r->title,
                    'answer'=>(string)$r->answer,
                    'score'=>0.0
                ])->all();

            return [false, $fallback[0] ?? ['id'=>0,'title'=>'','answer'=>'','score'=>0.0], $fallback];
        }

        // 2) Gabungkan kandidat by id
        $byId = [];
        foreach ($bm as $r) {
            $byId[$r['id']] = [
                'id'     => $r['id'],
                'title'  => $r['title'],
                'answer' => $r['answer'],
                'bm25'   => (float)$r['score'],
                'ann'    => 0.0,
            ];
        }
        foreach ($ann as $r) {
            if (!isset($byId[$r['id']])) {
                $byId[$r['id']] = [
                    'id'     => $r['id'],
                    'title'  => $r['title'],
                    'answer' => $r['answer'],
                    'bm25'   => 0.0,
                    'ann'    => (float)$r['score'],
                ];
            } else {
                $byId[$r['id']]['ann'] = (float)$r['score'];
                if (($byId[$r['id']]['title'] ?? '') === '' && ($r['title'] ?? '') !== '') {
                    $byId[$r['id']]['title'] = $r['title'];
                }
                if (($byId[$r['id']]['answer'] ?? '') === '' && ($r['answer'] ?? '') !== '') {
                    $byId[$r['id']]['answer'] = $r['answer'];
                }
            }
        }

        // 3) RRF + rata-rata + exact boost + GATING anchor
        $rankBM = $this->makeRankIndex($bm);
        $rankAN = $this->makeRankIndex($ann);

        $qNorm     = $this->norm($q);
        $qTokens   = Nlp::tokens($q);
        $anchors   = $this->selectAnchorsOrRaw($qTokens, $q, 3); // 2–3 anchor
        $qtSet     = array_flip($qTokens);

        foreach ($byId as $id => &$row) {
            $rb  = $rankBM[$id] ?? (count($bm)  + 1000);
            $ra  = $rankAN[$id] ?? (count($ann) + 1000);
            $rrf = (1.0 / (self::RRF_K + $rb)) + (1.0 / (self::RRF_K + $ra));
            $avg = 0.5 * ($row['bm25'] + $row['ann']);  // 0..1

            $score = 0.65 * $rrf + 0.35 * $avg;

            // Near-exact title boost
            $title = (string)($row['title'] ?? '');
            $titleNorm   = $this->norm($title);
            $titleTokens = Nlp::tokens($title);

            if ($titleNorm !== '') {
                if ($titleNorm === $qNorm) {
                    $score += self::EXACT_BOOST;
                } else {
                    $inter = count(array_intersect($qTokens, $titleTokens));
                    $union = count(array_unique(array_merge($qTokens, $titleTokens))) ?: 1;
                    $jacc  = $inter / $union;
                    if ($jacc >= 0.60 || str_contains($titleNorm, $qNorm)) {
                        $score += self::EXACT_BOOST * 0.6;
                    }
                }
            }

            // GATING: judul wajib mengandung minimal 1 token query
            $hasOverlap = false;
            foreach ($titleTokens as $t) {
                if (isset($qtSet[$t])) { $hasOverlap = true; break; }
            }
            if (!$hasOverlap) {
                $row['score'] = 0.0; // coret keras → mencegah “merembet”
                continue;
            }

            // Bonus bila SEMUA anchor muncul di judul
            $miss = 0;
            foreach ($anchors as $a) {
                if ($a !== '' && !in_array($a, $titleTokens, true)) $miss++;
            }
            if ($miss === 0 && !empty($anchors)) {
                $score = min(1.0, $score + 0.20);
            } elseif ($miss >= 2) {
                $score *= 0.60;
            } elseif ($miss === 1) {
                $score *= 0.75;
            }

            $row['score'] = min(1.0, max(0.0, $score));
        }
        unset($row);

        // 4) Urutkan, ambil top, normalisasi 0..1
        $list = array_values($byId);
        usort($list, fn($a,$b)=>($b['score'] <=> $a['score']));
        $top  = array_slice($list, 0, max(1, $take));
        $top  = $this->rescale($top, 'score');

        $best = $top[0];
        $sec  = $top[1] ?? null;

        // 5) Keputusan confidence “jawab langsung”
        $ok = false;
        if ($best['score'] >= self::CONF_MIN) {
            $ok = true;
        } elseif ($sec) {
            $gap = $best['score'] - $sec['score'];
            $rat = ($sec['score'] > 0) ? ($best['score'] / max($sec['score'], 1e-9)) : 2.0;
            if ($gap >= self::CONF_GAP || $rat >= self::CONF_RATIO) $ok = true;
        }

        return [
            $ok,
            [
                'id'     => (int)$best['id'],
                'title'  => (string)($best['title'] ?? ''),
                'answer' => (string)($best['answer'] ?? ''),
                'score'  => (float)$best['score'],
            ],
            $top,
        ];
    }

    /* ======================= BM25 — FULLTEXT JUDUL SAJA ======================= */

    protected function searchBM25(string $q, int $limit = self::BM25_TOPK): array
    {
        // Ambil anchor (DF → kalau kosong pakai raw word)
        $tokens  = Nlp::tokens($q);
        $anchors = $this->selectAnchorsOrRaw($tokens, $q, 3);
        $bigrams = $this->bigrams($q, 2);

        $bool = $this->toBooleanQuery($anchors, $bigrams);

        try {
            if ($bool !== '') {
                $rows = KIssue::query()
                    ->select([
                        'id',
                        DB::raw('issue_name AS title'),
                        DB::raw('solusion AS answer'),
                        DB::raw('MATCH(issue_name) AGAINST (? IN BOOLEAN MODE) AS ft_score')
                    ])
                    ->whereRaw('MATCH(issue_name) AGAINST (? IN BOOLEAN MODE)', [$bool])
                    ->orderByDesc('ft_score')
                    ->limit($limit)
                    ->get()
                    ->map(fn($r)=>[
                        'id'     => (int)$r->id,
                        'title'  => (string)$r->title,
                        'answer' => (string)$r->answer,
                        'score'  => (float)$r->ft_score,
                    ])
                    ->all();

                if (!empty($rows)) return $rows;
            }
        } catch (\Throwable) {
            // FULLTEXT belum tersedia → lanjut LIKE
        }

        // LIKE dengan AND untuk semua anchor (lebih presisi)
        if (!empty($anchors)) {
            return $this->andLikeAnchors($anchors, $limit);
        }

        // Fallback terakhir: LIKE long needle
        $like = '%' . Str::of($q)->lower()->replaceMatches('/\s+/u','%') . '%';
        return KIssue::query()
            ->select(['id', DB::raw('issue_name AS title'), DB::raw('solusion AS answer')])
            ->where('issue_name', 'like', $like)
            ->limit($limit)
            ->get()
            ->map(fn($r)=>[
                'id'     => (int)$r->id,
                'title'  => (string)$r->title,
                'answer' => (string)$r->answer,
                'score'  => 1.0,
            ])->all();
    }

    /** Pilih 2–3 anchor dari DF; jika kosong, pakai kata mentah dari query (≥3 huruf). */
    protected function selectAnchorsOrRaw(array $qTokens, string $q, int $max = 3): array
    {
        $df = Nlp::df();
        $cand = [];
        foreach ($qTokens as $t) {
            if (!isset($df[$t])) continue;
            if (mb_strlen($t) < 2) continue;
            $cand[$t] = $df[$t];
        }
        asort($cand);
        $anchors = array_slice(array_keys($cand), 0, max(2, $max));
        $anchors = array_values($anchors);

        if (!empty($anchors)) return $anchors;

        // Fallback: raw words dari query
        $raw = array_values(array_filter(
            explode(' ', $this->norm($q)),
            fn($w)=>mb_strlen($w) >= 3
        ));
        return array_slice($raw, 0, max(2, $max));
    }

    /** LIKE: semua anchor wajib muncul (AND). */
    protected function andLikeAnchors(array $anchors, int $limit): array
    {
        $q = KIssue::query()
            ->select(['id', DB::raw('issue_name AS title'), DB::raw('solusion AS answer')]);

        foreach ($anchors as $t) {
            $q->where('issue_name', 'like', '%'.$t.'%');
        }

        return $q->limit($limit)->get()
            ->map(fn($r)=>[
                'id'     => (int)$r->id,
                'title'  => (string)$r->title,
                'answer' => (string)$r->answer,
                'score'  => 1.0, // dinormalisasi nanti
            ])->all();
    }

    /** Bigram dari query (maks N). */
    protected function bigrams(string $q, int $take = 2): array
    {
        $ws = array_values(array_filter(explode(' ', $this->norm($q))));
        if (count($ws) < 2) return [];
        $b = [];
        for ($i = 0; $i < count($ws)-1; $i++) $b[] = $ws[$i].' '.$ws[$i+1];
        return array_slice($b, 0, $take);
    }

    /** Susun boolean query ringkas & ketat. */
    protected function toBooleanQuery(array $anchors, array $bigrams = []): string
    {
        $parts = [];
        foreach ($anchors as $t) {
            $t = trim($t);
            if ($t === '' || mb_strlen($t) < 2) continue;
            $parts[] = '+' . $t . '*';
        }
        foreach ($bigrams as $bg) {
            if (mb_strlen($bg) >= 4) $parts[] = '"' . $bg . '"';
        }
        return $parts ? implode(' ', $parts) : '';
    }

    /* ======================= ANN — EMBED JUDUL SAJA ======================= */

    protected function searchANN(string $q, int $limit = self::ANN_TOPK): array
    {
        try {
            $text = mb_substr($q, 0, 256);               // aman & cepat
            if ($text === '') return [];
            $vec  = $this->embedder->embed($text);
            if (empty($vec)) return [];

            $hits = $this->qdrant->search($vec, $limit);
            if (empty($hits)) return [];

            $out = [];
            foreach ($hits as $h) {
                $out[] = [
                    'id'     => (int)($h['id'] ?? 0),
                    'title'  => (string)($h['payload']['issue_name'] ?? $h['payload']['title'] ?? ''),
                    'answer' => (string)($h['payload']['solusion']   ?? $h['payload']['answer'] ?? ''),
                    'score'  => (float)($h['score'] ?? 0.0), // cosine sim
                ];
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /* ======================= Utils ======================= */

    protected function rescale(array $rows, string $key): array
    {
        if (empty($rows)) return $rows;
        $min = INF; $max = -INF;
        foreach ($rows as $r) {
            $v = (float)($r[$key] ?? 0.0);
            $min = min($min, $v); $max = max($max, $v);
        }
        if (!is_finite($min) || !is_finite($max) || $max <= $min) {
            foreach ($rows as &$r) $r[$key] = 1.0; unset($r);
            return $rows;
        }
        $den = $max - $min;
        foreach ($rows as &$r) {
            $r[$key] = max(0.0, min(1.0, ((float)$r[$key] - $min) / $den));
        }
        unset($r);
        return $rows;
    }

    protected function makeRankIndex(array $rows): array
    {
        $rank = []; $i = 1;
        foreach ($rows as $r) $rank[(int)$r['id']] = $i++;
        return $rank;
    }

    protected function norm(string $s): string
    {
        $s = Str::lower($s);
        $s = preg_replace('/[-_]/', ' ', $s);
        $s = preg_replace('/[^a-z0-9 ]+/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', trim($s));
        return $s ?? '';
    }
}
