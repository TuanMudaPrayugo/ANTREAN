<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Support\Nlp;
use App\Models\KIssue;
use App\Models\FaqFeedback;
use App\Services\LlmService;
use App\Services\KIssueMatcher;

class TanyaSaharController extends Controller
{
    public function index()
    {
        return view('user/tanyasahar/index', [
            'title'         => 'Tanya Sahar',
            'menuTanyaSahar'=> 'active',
        ]);
    }

    public function ask(Request $request, LlmService $llm)
{
    try {
        $q = trim((string) $request->input('q', ''));
        $tokens = \App\Support\Nlp::tokens($q); // tokenizer dinamis (alias & koreksi)

        // 0) Input kosong → kirim rekomendasi populer
        if ($q === '' || empty($tokens)) {
            $alts = $this->suggestIssues([], 6, null);
            return response()->json([
                'title'        => null,
                'answer'       => '',
                'issue_id'     => null,
                'alternatives' => $alts,
                'ask_feedback' => false,
            ], 200);
        }

        /* ========= 1) Deterministik (exact/alias) ========= */
        if ($hit = \App\Services\KIssueMatcher::exact($q)) {
            $related = $this->relatedFromResults($q, collect([$hit]), 5);
            // BACKUP jika related kosong
            if (empty($related)) {
                $related = $this->suggestIssues($tokens, 5, null);
                $related = $this->filterTopicSafeTitles($related, $tokens);
            }

            $raw  = (string) ($hit->solusion ?? '');
            $nice = $llm->polish($q, $raw);

            return response()->json([
                'title'        => $hit->issue_name,
                'answer'       => $nice,
                'issue_id'     => $hit->id,
                'alternatives' => $related,
                'ask_feedback' => true,
            ], 200);
        }

        /* ========= 2) SmartSearch + Guard ========= */
        $results = \App\Models\KIssue::smartSearch($q, 10);

        if ($results->isNotEmpty()) {
            // 2.a pilih beberapa anchor paling informatif (DF terkecil)
            $anchors = $this->pickAnchorsForQuery($tokens);

            // 2.b pilih kandidat teratas yang memuat SEMUA anchor
            $top = $this->chooseAnchoredTop($results, $anchors, $tokens);

            if ($top) {
                $ok = $this->isConfidentMatch($tokens, $top, $results);

                // 2.c Guard tambahan: token penting (anchor + token panjang) harus ada di kandidat
                $haystack = \Illuminate\Support\Str::lower(
                    ($top->issue_name ?? '') . ' ' . ($top->solusion ?? '')
                );
                $mustTokens = $anchors;
                foreach ($tokens as $t) if (mb_strlen($t) >= 5) $mustTokens[] = $t;
                $mustTokens = array_values(array_unique($mustTokens));

                foreach ($mustTokens as $t) {
                    if (!\Illuminate\Support\Str::contains($haystack, $t)) {
                        $ok = false; break;
                    }
                }

                if ($ok) {
                    // 2.d siapkan related dari pool selain top dan tetap jaga anchor
                    $relatedPool = $results->reject(fn ($r) => $r->id === $top->id)->values();
                    if (!empty($anchors)) {
                        $relatedPool = $relatedPool->filter(function ($r) use ($anchors) {
                            $hay = \Illuminate\Support\Str::lower(
                                ($r->issue_name ?? '') . ' ' . ($r->solusion ?? '')
                            );
                            foreach ($anchors as $a) {
                                if (!\Illuminate\Support\Str::contains($hay, $a)) return false;
                            }
                            return true;
                        })->values();
                    }

                    $related = $this->relatedFromResults($q, $relatedPool, 5);
                    // BACKUP related jika kosong
                    if (empty($related)) {
                        $related = $this->suggestIssues($tokens, 5, null);
                        $related = $this->filterTopicSafeTitles($related, $tokens);
                    }

                    $raw  = (string) ($top->solusion ?? '');
                    $nice = $llm->polish($q, $raw);

                    return response()->json([
                        'title'        => $top->issue_name,
                        'answer'       => $nice,
                        'issue_id'     => $top->id,
                        'alternatives' => $related,
                        'ask_feedback' => true,
                    ], 200);
                }
            }
        }

        /* ========= 2b) Soft title cover (judul mencakup ≥80% token kueri) ========= */
        $soft = \App\Models\KIssue::tokenSearch($q, 20);
        if ($soft->isNotEmpty()) {
            $best = null; $bestCov = 0.0; $qT = \App\Support\Nlp::tokens($q);

            foreach ($soft as $r) {
                $tT = \App\Support\Nlp::tokens($r->issue_name ?? '');
                $inter = count(array_intersect($qT, $tT));
                $cov   = $inter / max(1, count($qT));
                if ($cov > $bestCov) { $bestCov = $cov; $best = $r; }
            }

            if ($best && $bestCov >= 0.80) {
                $related = $this->relatedFromResults($q, $soft->reject(fn ($x) => $x->id === $best->id), 5);
                // BACKUP related jika kosong
                if (empty($related)) {
                    $related = $this->suggestIssues($qT, 5, null);
                    $related = $this->filterTopicSafeTitles($related, $qT);
                }

                $raw  = (string) ($best->solusion ?? '');
                $nice = $llm->polish($q, $raw);

                return response()->json([
                    'title'        => $best->issue_name,
                    'answer'       => $nice,
                    'issue_id'     => $best->id,
                    'alternatives' => $related,
                    'ask_feedback' => true,
                ], 200);
            }
        }

        /* ========= 3) Fallback (tidak ada hasil yang cukup) ========= */
        $alts = $this->suggestIssues($tokens, 6, null);
        $alts = $this->filterTopicSafeTitles($alts, $tokens);
        // BACKOFF: kalau setelah filter kosong, tetap kirim saran mentah
        if (empty($alts)) $alts = $this->suggestIssues($tokens, 6, null);

        return response()->json([
            'title'        => null,
            'answer'       => '',
            'issue_id'     => null,
            'alternatives' => $alts,
            'ask_feedback' => false,
        ], 200);

    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('ASK failed', [
            'q'     => $request->input('q'),
            'error' => $e->getMessage(),
        ]);

        $tokens = \App\Support\Nlp::tokens((string) $request->input('q', ''));
        $alts   = $this->suggestIssues($tokens, 6, null);

        return response()->json([
            'title'        => null,
            'answer'       => '',
            'issue_id'     => null,
            'alternatives' => $alts,
            'ask_feedback' => false,
        ], 200);
    }
}


protected function filterTopicSafeTitles(array $titles, array $qTokens): array
{
    if (empty($titles)) return [];
    $df   = \App\Support\Nlp::df();
    $qSet = array_flip($qTokens);

    $safe = [];
    foreach ($titles as $title) {
        $titleTokens = \App\Support\Nlp::tokens($title);
        $cand = [];
        foreach ($titleTokens as $t) if (isset($df[$t])) $cand[$t] = $df[$t];
        if (empty($cand)) { $safe[] = $title; continue; }

        asort($cand); // DF kecil = lebih spesifik
        $titleAnchors = array_slice(array_keys($cand), 0, 2);

        $hasNewSpecific = false;
        foreach ($titleAnchors as $t) {
            if (!isset($qSet[$t])) { $hasNewSpecific = true; break; }
        }
        if (!$hasNewSpecific) $safe[] = $title;
    }

    return array_values(array_unique($safe));
}

/* ===================== Helper baru ===================== */

/**
 * Pilih 1–3 anchor paling informatif dari token kueri
 * (berdasar DF terendah → paling spesifik).
 */
protected function pickAnchorsForQuery(array $qTokens): array
{
    $df = \App\Support\Nlp::df();
    $cand = [];
    foreach ($qTokens as $t) {
        if (!isset($df[$t])) continue;
        $cand[$t] = $df[$t];
    }
    // urutkan dari DF kecil (lebih informatif)
    asort($cand);
    // ambil maksimal 3 anchor
    return array_slice(array_keys($cand), 0, 3);
}

/**
 * Dari hasil smartSearch, pilih kandidat teratas yang memuat SEMUA anchor.
 * Jika tidak ada yang memenuhi, kembalikan null (agar jatuh ke fallback).
 */
protected function chooseAnchoredTop($results, array $anchors, array $qTokens = [])
{
    if (empty($results)) return null;

    $qSet = array_flip($qTokens);
    $df   = \App\Support\Nlp::df();

    foreach ($results as $r) {
        $title = \Illuminate\Support\Str::lower($r->issue_name ?? '');
        $hay   = \Illuminate\Support\Str::lower(($r->issue_name ?? '') . ' ' . ($r->solusion ?? ''));

        // 1) wajib mengandung semua anchor dari query
        $ok = true;
        foreach ($anchors as $a) {
            if (!\Illuminate\Support\Str::contains($hay, $a)) { $ok = false; break; }
        }
        if (!$ok) continue;

        // 2) 🔒 tolak jika judul membawa token spesifik (DF rendah) yang tak ada di query
        $titleTokens = \App\Support\Nlp::tokens($title);
        $cand = [];
        foreach ($titleTokens as $t) if (isset($df[$t])) $cand[$t] = $df[$t];
        asort($cand); // DF terkecil = paling spesifik
        $titleAnchors = array_slice(array_keys($cand), 0, 2);

        $hasNewSpecific = false;
        foreach ($titleAnchors as $t) {
            if (!isset($qSet[$t])) { $hasNewSpecific = true; break; }
        }
        if ($hasNewSpecific) continue;

        return $r;
    }

    return null;
}


    /**
     * Simpan feedback Ya/Tidak dari user
     */
    public function feedback(Request $request)
    {
        $data = $request->validate([
            'issue_id'     => 'nullable|integer',
            'session_id'   => 'nullable|string|max:64',
            'user_query'   => 'required|string',
            'is_helpful'   => 'required|boolean',
            'alternatives' => 'array',
        ]);

        $fb = FaqFeedback::create([
            'issue_id'     => $data['issue_id'] ?? null,
            'session_id'   => $data['session_id'] ?? null,
            'user_query'   => $data['user_query'],
            'is_helpful'   => $data['is_helpful'],
            'alternatives' => $data['alternatives'] ?? [],
        ]);

        return response()->json(['saved' => true, 'id' => $fb->id], 201);
    }

    /* ===================== Helpers ===================== */

    protected function isConfidentMatch(array $qTokens, $top, $results): bool
{
    $hayTokens = \App\Support\Nlp::tokens(($top->issue_name ?? '') . ' ' . ($top->solusion ?? ''));
    $inter     = count(array_intersect($qTokens, $hayTokens));
    $union     = count(array_unique(array_merge($qTokens, $hayTokens))) ?: 1;
    $jacc      = $inter / $union;

    $s1    = (float) ($top->score ?? 0.0);
    $s2obj = optional($results->skip(1)->first());
    $s2    = (float) ($s2obj->score ?? 0.0);
    $gap   = $s1 - $s2;
    $ratio = $s2 > 0 ? $s1 / max($s2, 1e-9) : 2.0;

    $need = min(0.55, max(0.20, 0.12 + 0.05 * count($qTokens)));

    // === Tambahkan guard penting ===
    $mustTokens = [];
    foreach ($qTokens as $t) {
        if (in_array($t, ['alamat','mutasi','pindah'])) {
            $mustTokens[] = $t;
        }
    }
    foreach ($mustTokens as $t) {
        if (!in_array($t, $hayTokens)) {
            return false; // kandidat tolak kalau token penting tidak ada
        }
    }

    $anchor    = \App\Support\Nlp::pickAnchor($qTokens);
    $haystack  = \Illuminate\Support\Str::lower(($top->issue_name ?? '') . ' ' . ($top->solusion ?? ''));
    $anchorHit = $anchor && \Illuminate\Support\Str::contains($haystack, $anchor);

    if (count($qTokens) <= 2 || $anchorHit) {
        return ($jacc >= 0.18) || ($ratio >= 1.20) || ($gap >= 0.12);
    }

    return ($jacc >= $need) || ($ratio >= 1.35) || ($gap >= 0.18);
}


    protected function relatedFromResults(string $q, $cands, int $take = 5): array
{
    $asked = collect(\App\Support\Nlp::tokens($q));

    $cand = collect($cands)->map(function ($r) use ($asked) {
        $t = collect(\App\Support\Nlp::tokens($r->issue_name))->unique()->values()->all();
        $inter = count(array_intersect($asked->all(), $t));
        $union = count(array_unique(array_merge($asked->all(), $t))) ?: 1;
        $sim   = $inter / $union;
        return ['title' => $r->issue_name, 'sim' => $sim];
    });

    if ($cand->isEmpty()) return [];

    $best   = $cand->max('sim');
    $median = $cand->median('sim');
    $cut    = max(0.60 * $best, 0.80 * $median);

    $titles0 = $cand->filter(fn ($x) => $x['sim'] >= $cut)
                    ->sortByDesc('sim')
                    ->pluck('title')
                    ->take($take * 2)
                    ->values()
                    ->all();

    // 1st pass: saring “setema”
    $titles = $this->filterTopicSafeTitles($titles0, $asked->all());
    $titles = array_slice($titles, 0, $take);

    // Fallback jika kosong → pakai versi TIDAK disaring (biar selalu ada)
    if (empty($titles)) {
        $titles = array_slice(array_values(array_unique($titles0)), 0, $take);
    }

    return $titles;
}


    protected function suggestIssues(array $tokens, int $limit = 6, $results = null): array
{
    $titles = [];

    // fulltext
    if (!empty($tokens)) {
        $bool = collect($tokens)->map(fn ($t) => $t . '*')->implode(' ');
        $titles = \App\Models\KIssue::select('issue_name')
            ->whereRaw("MATCH(issue_name, solusion) AGAINST (? IN BOOLEAN MODE)", [$bool])
            ->orderByRaw("MATCH(issue_name, solusion) AGAINST (? IN BOOLEAN MODE) DESC", [$bool])
            ->limit($limit * 3)
            ->pluck('issue_name')->unique()->values()->all();
    }

    // like
    if (empty($titles) && !empty($tokens)) {
        $q = \App\Models\KIssue::query();
        foreach ($tokens as $t) $q->orWhere('issue_name', 'like', "%{$t}%");
        $titles = $q->limit($limit * 3)->pluck('issue_name')->unique()->values()->all();
    }

    // terbaru
    if (empty($titles)) {
        $titles = \App\Models\KIssue::orderByDesc('id')->limit($limit * 3)->pluck('issue_name')->all();
    }

    // 1st pass: filter setema
    $filtered = $this->filterTopicSafeTitles($titles, $tokens);
    $filtered = array_slice($filtered, 0, $limit);

    // Fallback jika kosong → balik ke daftar awal (tanpa filter) agar tetap ada saran
    if (empty($filtered)) {
        $filtered = array_slice(array_values(array_unique($titles)), 0, $limit);
    }

    return $filtered;
}


   
    
}
