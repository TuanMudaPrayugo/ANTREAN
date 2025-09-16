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
        $tokens = Nlp::tokens($q); // sudah dinamis (alias & koreksi dari Nlp)

        // Input kosong → rekomendasi populer
        if ($q === '' || empty($tokens)) {
            return response()->json([
                'title'        => null,
                'answer'       => '',
                'issue_id'     => null,
                'alternatives' => $this->suggestIssues([], 6),
                'ask_feedback' => false,
            ], 200);
        }

        /* ========= 1) Deterministik (exact/anchor coverage) ========= */
        if ($hit = \App\Services\KIssueMatcher::exact($q)) {
            $related = $this->relatedFromResults($q, collect([$hit]), 5);
            $raw     = (string) ($hit->solusion ?? '');
            $nice    = $llm->polish($q, $raw);

            return response()->json([
                'title'        => $hit->issue_name,
                'answer'       => $nice,
                'issue_id'     => $hit->id,
                'alternatives' => $related,
                'ask_feedback' => true,
            ], 200);
        }

        /* ========= 2) SmartSearch + Guard ========= */
        $results = KIssue::smartSearch($q, 10);

        if ($results->isNotEmpty()) {
            // pilih beberapa anchor paling informatif (DF terkecil)
            $anchors = $this->pickAnchorsForQuery($tokens);

            // pilih kandidat teratas yang mengandung SEMUA anchor
            $top = $this->chooseAnchoredTop($results, $anchors);

            if ($top) {
                $ok = $this->isConfidentMatch($tokens, $top, $results);

                // 🔒 Guard tambahan: token penting harus muncul pada kandidat
                $haystack = \Illuminate\Support\Str::lower(
                    ($top->issue_name ?? '') . ' ' . ($top->solusion ?? '')
                );

                // token penting = anchor + token kueri panjang (≥5)
                $mustTokens = $anchors;
                foreach ($tokens as $t) {
                    if (mb_strlen($t) >= 5) $mustTokens[] = $t;
                }
                $mustTokens = array_values(array_unique($mustTokens));

                foreach ($mustTokens as $t) {
                    if (!\Illuminate\Support\Str::contains($haystack, $t)) {
                        $ok = false; // jika SATU token penting tidak ada → jangan paksa
                        break;
                    }
                }

                if ($ok) {
                    // related: ambil dari pool selain top dan tetap menjaga anchor
                    $relatedPool = $results->reject(fn($r)=>$r->id === $top->id)->values();
                    if (!empty($anchors)) {
                        $relatedPool = $relatedPool->filter(function($r) use ($anchors){
                            $hay = \Illuminate\Support\Str::lower(
                                ($r->issue_name ?? '').' '.($r->solusion ?? '')
                            );
                            foreach ($anchors as $a) {
                                if (!\Illuminate\Support\Str::contains($hay, $a)) return false;
                            }
                            return true;
                        })->values();
                    }

                    $related = $this->relatedFromResults($q, $relatedPool, 5);
                    $raw     = (string) ($top->solusion ?? '');
                    $nice    = $llm->polish($q, $raw); // LLM safe-polish

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

        /* ========= 3) Fallback: tidak ada hasil yang cukup ========= */
        return response()->json([
            'title'        => null,
            'answer'       => '',
            'issue_id'     => null,
            'alternatives' => $this->suggestIssues($tokens, 6, $results ?? null),
            'ask_feedback' => false,
        ], 200);

    } catch (\Throwable $e) {
        Log::error('ASK failed', [
            'q'     => $request->input('q'),
            'error' => $e->getMessage(),
        ]);

        $tokens = Nlp::tokens((string) $request->input('q', ''));
        return response()->json([
            'title'        => null,
            'answer'       => '',
            'issue_id'     => null,
            'alternatives' => $this->suggestIssues($tokens, 6),
            'ask_feedback' => false,
        ], 200);
    }
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
protected function chooseAnchoredTop($results, array $anchors)
{
    if (empty($anchors)) {
        return $results->first(); // tanpa anchor, pakai skor tertinggi
    }

    foreach ($results as $r) {
        $hay = \Illuminate\Support\Str::lower(
            ($r->issue_name ?? '') . ' ' . ($r->solusion ?? '')
        );
        $ok = true;
        foreach ($anchors as $a) {
            if (!\Illuminate\Support\Str::contains($hay, $a)) { $ok = false; break; }
        }
        if ($ok) return $r;
    }
    return null; // tidak ada kandidat yang mengandung semua anchor
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
        $hayTokens = Nlp::tokens(($top->issue_name ?? '') . ' ' . ($top->solusion ?? ''));
        $inter     = count(array_intersect($qTokens, $hayTokens));
        $union     = count(array_unique(array_merge($qTokens, $hayTokens))) ?: 1;
        $jacc      = $inter / $union;

        $s1    = (float) ($top->score ?? 0.0);
        $s2obj = optional($results->skip(1)->first());
        $s2    = (float) ($s2obj->score ?? 0.0);
        $gap   = $s1 - $s2;
        $ratio = $s2 > 0 ? $s1 / max($s2, 1e-9) : 2.0;

        // default threshold
        $need = min(0.55, max(0.20, 0.12 + 0.05 * count($qTokens)));

        // pelunak untuk kueri pendek atau anchor match
        $anchor    = Nlp::pickAnchor($qTokens);
        $haystack  = Str::lower(($top->issue_name ?? '') . ' ' . ($top->solusion ?? ''));
        $anchorHit = $anchor && Str::contains($haystack, $anchor);

        if (count($qTokens) <= 2 || $anchorHit) {
            return ($jacc >= 0.18) || ($ratio >= 1.20) || ($gap >= 0.12);
        }

        return ($jacc >= $need) || ($ratio >= 1.35) || ($gap >= 0.18);
    }

    protected function relatedFromResults(string $q, $cands, int $take = 5): array
    {
        $asked = collect(Nlp::tokens($q));

        $cand = collect($cands)->map(function ($r) use ($asked) {
            $t = collect(Nlp::tokens($r->issue_name))->unique()->values()->all();
            $inter = count(array_intersect($asked->all(), $t));
            $union = count(array_unique(array_merge($asked->all(), $t))) ?: 1;
            $sim   = $inter / $union;
            return ['title' => $r->issue_name, 'sim' => $sim];
        });

        if ($cand->isEmpty()) return [];

        $best   = $cand->max('sim');
        $median = $cand->median('sim');
        $cut    = max(0.60 * $best, 0.80 * $median);

        return $cand->filter(fn ($x) => $x['sim'] >= $cut)
                    ->sortByDesc('sim')
                    ->pluck('title')
                    ->take($take)
                    ->values()
                    ->all();
    }

    protected function suggestIssues(array $tokens, int $limit = 6, $results = null): array
    {
        // 1) FULLTEXT boolean
        if (!empty($tokens)) {
            $bool = collect($tokens)->map(fn ($t) => $t . '*')->implode(' ');
            $s1 = KIssue::select('issue_name')
                ->whereRaw("MATCH(issue_name, solusion) AGAINST (? IN BOOLEAN MODE)", [$bool])
                ->orderByRaw("MATCH(issue_name, solusion) AGAINST (? IN BOOLEAN MODE) DESC", [$bool])
                ->limit($limit * 2)
                ->pluck('issue_name')
                ->unique()
                ->take($limit)
                ->values()
                ->all();
            if (!empty($s1)) return $s1;
        }

        // 2) LIKE per token
        if (!empty($tokens)) {
            $q = KIssue::query();
            foreach ($tokens as $t) $q->orWhere('issue_name', 'like', "%{$t}%");
            $s2 = $q->limit($limit * 2)->pluck('issue_name')->unique()->take($limit)->values()->all();
            if (!empty($s2)) return $s2;
        }

        // 3) dari pool hasil awal atau fallback terbaru
        if ($results && $results->isNotEmpty()) {
            return $results->pluck('issue_name')->unique()->take($limit)->values()->all();
        }

        return KIssue::orderByDesc('id')->limit($limit)->pluck('issue_name')->all();
    }

   
    
}
