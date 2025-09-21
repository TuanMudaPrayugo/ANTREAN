<?php

namespace App\Http\Controllers;

use App\Support\Nlp;
use App\Models\KIssue;
use App\Models\FaqFeedback;
use Illuminate\Support\Str;
use App\Services\LlmService;
use Illuminate\Http\Request;
use App\Services\HybridSearch;
use App\Services\KIssueMatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TanyaSaharController extends Controller
{
    /** ====== Konstanta (tanpa ENV) ====== */
    private const CACHE_TTL_SECONDS   = 45;

    // Keputusan “jawab langsung” (disetel agresif tapi aman)
    private const CONF_MIN_SCORE      = 0.62;   // min skor best (0..1) utk auto-jawab
    private const CONF_MIN_GAP        = 0.12;   // min selisih best-second
    private const CONF_MIN_RATIO      = 1.35;   // min rasio best/second

    // Pesan standar
    private const MSG_TOO_SHORT       = 'Pertanyaannya terlalu singkat. Pilih salah satu yang mendekati:';
    private const MSG_SUGGEST         = 'Mungkin yang Anda maksud:';

    public function index()
    {
        return view('user/tanyasahar/index', [
            'title'          => 'Tanya Sahar',
            'menuTanyaSahar' => 'active',
        ]);
    }

    /** ====== Endpoint utama ====== */
    public function ask(Request $request, LlmService $llm, HybridSearch $hybrid)
    {
        try {
            $q = trim((string) $request->input('q', ''));
            $cacheKey = 'ts:ask:v2:' . md5(mb_strtolower($q));

            $payload = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($q, $llm, $hybrid) {
                // 0) Kosong / terlalu pendek → rekomendasi populer
                $tokens = Nlp::tokens($q);
                // 1a) Pre-check: semua "anchor" muncul di judul
                $anchors = $this->pickAnchorsForQuery($tokens);
                if (!empty($anchors)) {
                    $query = KIssue::query()->select(['id','issue_name','solusion']);
                    foreach ($anchors as $a) {
                        if ($a !== '') $query->where('issue_name', 'like', '%'.$a.'%'); // AND semua anchor
                    }
                    $hitA = $query->orderBy('id','desc')->first();
                    if ($hitA) {
                        $raw  = (string)($hitA->solusion ?? '');
                        $nice = $llm->polish($q, $raw) ?: $raw;

                        return [
                            'title'        => (string)$hitA->issue_name,
                            'answer'       => $nice,
                            'issue_id'     => (int)$hitA->id,
                            'alternatives' => $this->ensureAlternatives($q, $tokens, $hitA->issue_name, 5),
                            'ask_feedback' => true,
                        ];
                    }
                }


                if ($q === '' || count($tokens) < 1) {
                    return [
                        'title'        => null,
                        'answer'       => self::MSG_TOO_SHORT,
                        'issue_id'     => null,
                        'alternatives' => $this->ensureAlternatives('', [], null, 8),
                        'ask_feedback' => false,
                    ];
                }

                // 1) Exact match (cepat & paling presisi)
                if ($hit = KIssueMatcher::exact($q)) {
                    $raw  = (string) ($hit->solusion ?? '');
                    $nice = $llm->polish($q, $raw) ?: $raw;
                    
                    $this->maybeLearnAlias($hit,$q);

                    return [
                        'title'        => $hit->issue_name,
                        'answer'       => $nice,
                        'issue_id'     => (int) $hit->id,
                        'alternatives' => $this->ensureAlternatives($q, $tokens, $hit->issue_name, 5),
                        'ask_feedback' => true,
                    ];
                }

                // 2) Hybrid (BM25 title + ANN title → RRF) — selalu mengembalikan top
                [$ok, $best, $top] = $hybrid->query($q, 5);

                if (!empty($best) && !empty($best['title'])) {
                    [$confident, $alts] = $this->decide($top, $q, $tokens);

                    if ($confident) {
                        $nice = $llm->polish($q, (string) ($best['answer'] ?? '')) ?: (string) ($best['answer'] ?? '');
                        return [
                            'title'        => (string) $best['title'],
                            'answer'       => $nice !== '' ? $nice : 'Maaf, belum ada jawaban.',
                            'issue_id'     => (int) $best['id'],
                            'alternatives' => $alts,
                            'ask_feedback' => true,
                        ];
                    }

                    // Tidak cukup yakin → tampilkan rekomendasi pertanyaan (tanpa jawaban)
                    return [
                        'title'        => null,
                        'answer'       => self::MSG_SUGGEST,
                        'issue_id'     => null,
                        'alternatives' => $alts,
                        'ask_feedback' => false,
                    ];
                }

                // 3) Emergency fallback → jangan pernah kosong
                return [
                    'title'        => null,
                    'answer'       => self::MSG_SUGGEST,
                    'issue_id'     => null,
                    'alternatives' => $this->ensureAlternatives($q, $tokens, null, 8),
                    'ask_feedback' => false,
                ];
            });

            return response()->json($payload, 200);
        } catch (\Throwable $e) {
            Log::error('ASK failed', ['q' => $request->input('q'), 'err' => $e->getMessage()]);

            // Darurat: tetap kasih rekomendasi agar UI tidak “buntu”
            return response()->json([
                'title'        => null,
                'answer'       => self::MSG_SUGGEST,
                'issue_id'     => null,
                'alternatives' => $this->ensureAlternatives((string) $request->input('q', ''), [], null, 8),
                'ask_feedback' => false,
            ], 200);
        }
    }

    /**
     * Keputusan “jawab langsung” vs “rekomendasi”
     * @param array $top  Hasil rank dari Hybrid (sudah ada score 0..1)
     * @return array{0:bool,1:array} [$confident, $alternatives]
     */
    private function decide(array $top, string $q, array $tokens): array
{
    // Ambang hard-coded (stabil)
    $CONF_MIN   = 0.55;
    $CONF_GAP   = 0.08;
    $CONF_RATIO = 1.20;

    $best   = $top[0] ?? null;
    $second = $top[1] ?? null;

    $bestScore = (float)($best['score'] ?? 0.0);
    $secScore  = (float)($second['score'] ?? 0.0);
    $gap       = $bestScore - $secScore;
    $ratio     = $secScore > 0 ? ($bestScore / max($secScore, 1e-9)) : 2.0;

    $confident = ($bestScore >= $CONF_MIN) || ($gap >= $CONF_GAP) || ($ratio >= $CONF_RATIO);

    // ✳️ Perketat: judul kandidat best harus memuat ≥1 token query
    $titleTokens = \App\Support\Nlp::tokens(mb_strtolower((string)($best['title'] ?? '')));
    $qSet = array_flip($tokens);
    $hasOverlap = false;
    foreach ($titleTokens as $t) { if (isset($qSet[$t])) { $hasOverlap = true; break; } }
    if (!$hasOverlap) $confident = false;

    // ✳️ Tambah gerbang: bila SEMUA anchor (2–3 kata informatif) ada di judul → auto true
    $anchors = $this->pickAnchorsForQuery($tokens);
    if (!empty($anchors)) {
        $okAll = true;
        foreach ($anchors as $a) {
            if ($a !== '' && !in_array($a, $titleTokens, true)) { $okAll = false; break; }
        }
        if ($okAll) $confident = true;
    }

    // Alternatif unik dari $top; jika kurang → isi lewat ensureAlternatives()
    $alts = array_values(array_unique(array_filter(array_map(
        fn ($r) => (string)($r['title'] ?? ''), array_slice($top, 0, 8)
    ))));

    if (count($alts) < 5) {
        $alts = $this->ensureAlternatives($q, $tokens, $best['title'] ?? null, 8);
    } else {
        $alts = array_values(array_filter($alts, fn ($t) => $t !== ($best['title'] ?? null)));
        $alts = array_slice($alts, 0, 5);
    }

    return [$confident, $alts];
}

    /** ====== FEEDBACK ====== */
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

        if (!$data['is_helpful']) {
            $tokens = Nlp::tokens($data['user_query']);
            $alts   = $this->ensureAlternatives($data['user_query'], $tokens, null, 5);
            return response()->json(['saved' => true, 'id' => $fb->id, 'alternatives' => $alts], 201);
        }

        return response()->json(['saved' => true, 'id' => $fb->id], 201);
    }

    /* ===================== Helpers ===================== */

    protected function ensureAlternatives(string $q, array $tokens, ?string $excludeTitle = null, int $min = 5): array
    {
        $norm = fn (string $s) => mb_strtolower(trim($s));

        $alts = KIssue::similarQuestions($q, $min + 5);

        if ($excludeTitle) {
            $ex   = $norm($excludeTitle);
            $alts = array_values(array_filter($alts, fn ($t) => $norm($t) !== $ex));
        }

        if (count($alts) < $min) {
            $more = $this->suggestIssues($tokens ?: Nlp::tokens($q), $min + 5);
            if ($excludeTitle) {
                $ex   = $norm($excludeTitle);
                $more = array_values(array_filter($more, fn ($t) => $norm($t) !== $ex));
            }
            $seen = [];
            foreach (array_merge($alts, $more) as $t) {
                $k = $norm($t);
                if (!isset($seen[$k])) $seen[$k] = $t;
            }
            $alts = array_values($seen);
        }

        return array_slice($alts, 0, $min);
    }

    protected function filterTopicSafeTitles(array $titles, array $qTokens): array
    {
        if (empty($titles)) return [];
        $df   = Nlp::df();
        $qSet = array_flip($qTokens);

        $safe = [];
        foreach ($titles as $title) {
            $titleTokens = Nlp::tokens($title);
            $cand        = [];
            foreach ($titleTokens as $t) if (isset($df[$t])) $cand[$t] = $df[$t];
            if (empty($cand)) { $safe[] = $title; continue; }

            asort($cand);
            $titleAnchors = array_slice(array_keys($cand), 0, 2);

            $hasNewSpecific = false;
            foreach ($titleAnchors as $t) {
                if (!isset($qSet[$t])) { $hasNewSpecific = true; break; }
            }
            if (!$hasNewSpecific) $safe[] = $title;
        }

        return array_values(array_unique($safe));
    }

    protected function pickAnchorsForQuery(array $qTokens): array
    {
        $df   = Nlp::df();
        $cand = [];
        foreach ($qTokens as $t) {
            if (!isset($df[$t])) continue;
            $cand[$t] = $df[$t];
        }
        asort($cand);
        return array_slice(array_keys($cand), 0, 3);
    }

    protected function chooseAnchoredTop($results, array $anchors, array $qTokens = [])
    {
        if (empty($results)) return null;

        $qSet = array_flip($qTokens);
        $df   = Nlp::df();

        foreach ($results as $r) {
            $title = Str::lower($r->issue_name ?? '');
            $hay   = Str::lower(($r->issue_name ?? '') . ' ' . ($r->solusion ?? ''));

            $ok = true;
            foreach ($anchors as $a) {
                if (!Str::contains($hay, $a)) { $ok = false; break; }
            }
            if (!$ok) continue;

            $titleTokens = Nlp::tokens($title);
            $cand        = [];
            foreach ($titleTokens as $t) if (isset($df[$t])) $cand[$t] = $df[$t];
            asort($cand);
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

        return ($jacc >= 0.20) || ($ratio >= 1.20) || ($gap >= 0.12);
    }

    protected function suggestIssues(array $tokens, int $limit = 6, $results = null): array
    {
        // Fokus ke judul (consisten dengan Hybrid & Qdrant)
        $titles = [];

        if (!empty($tokens)) {
            $bool   = collect($tokens)->map(fn ($t) => $t . '*')->implode(' ');
            $titles = KIssue::select('issue_name')
                ->whereRaw("MATCH(issue_name) AGAINST (? IN BOOLEAN MODE)", [$bool])
                ->orderByRaw("MATCH(issue_name) AGAINST (? IN BOOLEAN MODE) DESC", [$bool])
                ->limit($limit * 3)
                ->pluck('issue_name')->unique()->values()->all();
        }

        if (empty($titles) && !empty($tokens)) {
            $q = KIssue::query()->select('issue_name');
            foreach ($tokens as $t) $q->orWhere('issue_name', 'like', "%{$t}%");
            $titles = $q->limit($limit * 3)->pluck('issue_name')->unique()->values()->all();
        }

        if (empty($titles)) {
            $titles = KIssue::orderByDesc('id')->limit($limit * 3)->pluck('issue_name')->all();
        }

        $filtered = $this->filterTopicSafeTitles($titles, $tokens);
        $filtered = array_slice($filtered, 0, $limit);

        if (empty($filtered)) {
            $filtered = array_slice(array_values(array_unique($titles)), 0, $limit);
        }

        return $filtered;
    }

    private function learnableAlias(string $q, array $qTokens, array $titleTokens): ?string
{
    $norm = \App\Models\KIssue::normalizePhrase($q);
    if ($norm === '' || mb_strlen($norm) < 4) return null;     // terlalu pendek
    if (count($qTokens) === 0) return null;

    // stopwords ringan: cegah frasa super-generik masuk
    $stop = ['apa','bagaimana','gimana','dimana','kapan','siapa','yang','itu','dan','atau','di','ke','untuk','cara','mau'];
    $nonStop = array_diff($qTokens, $stop);
    if (count($nonStop) < 2) return null;

    // harus ada overlap token dengan judul kandidat
    if (count(array_intersect($qTokens, $titleTokens)) === 0) return null;

    return $norm;
}

private function maybeLearnAlias(\App\Models\KIssue $issue, string $q): void
{
    try {
        $qTokens     = \App\Support\Nlp::tokens($q);
        $titleTokens = \App\Support\Nlp::tokens((string)$issue->issue_name);
        $aliasNorm   = $this->learnableAlias($q, $qTokens, $titleTokens);
        if ($aliasNorm) {
            // non-blocking, supaya respons tetap cepat
            dispatch(function () use ($issue, $aliasNorm) {
                try { $issue->appendAlias($aliasNorm); } catch (\Throwable $e) {}
            });
        }
    } catch (\Throwable $e) {}
}
}
