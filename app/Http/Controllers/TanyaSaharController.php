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
            $tokens = Nlp::tokens($q);

            // 0) Input kosong → tampilkan pertanyaan populer/mirip
            if ($q === '' || empty($tokens)) {
                return response()->json([
                    'title'        => null,
                    'answer'       => '',
                    'issue_id'     => null,
                    'alternatives' => $this->ensureAlternatives('', [], null, 8),
                    'ask_feedback' => false,
                ], 200);
            }

            /* ========= 1) Exact Match ========= */
            if ($hit = KIssueMatcher::exact($q)) {
                $related = $this->ensureAlternatives($q, $tokens, $hit->issue_name, 5);

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

            /* ========= 2) SmartSearch ========= */
            $results = KIssue::smartSearch($q, 10);

            if ($results->isNotEmpty()) {
                $anchors = $this->pickAnchorsForQuery($tokens);
                $top     = $this->chooseAnchoredTop($results, $anchors, $tokens);
                if (!$top) $top = $results->first();

                if ($top && $this->isConfidentMatch($tokens, $top, $results)) {
                    $related = $this->ensureAlternatives($q, $tokens, $top->issue_name, 5);

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

            /* ========= 2b) Soft title cover ========= */
            $soft = KIssue::tokenSearch($q, 20);
            if ($soft->isNotEmpty()) {
                $best = null; $bestCov = 0.0; $qT = Nlp::tokens($q);

                foreach ($soft as $r) {
                    $tT = Nlp::tokens($r->issue_name ?? '');
                    $inter = count(array_intersect($qT, $tT));
                    $cov   = $inter / max(1, count($qT));
                    if ($cov > $bestCov) { $bestCov = $cov; $best = $r; }
                }

                if ($best && $bestCov >= 0.80) {
                    $related = $this->ensureAlternatives($q, $tokens, $best->issue_name, 5);

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

            /* ========= 3) Fallback ========= */
            $alts = $this->ensureAlternatives($q, $tokens, null, 8);
            return response()->json([
                'title'        => null,
                'answer'       => '',
                'issue_id'     => null,
                'alternatives' => $alts,
                'ask_feedback' => false,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('ASK failed', [
                'q'     => $request->input('q'),
                'error' => $e->getMessage(),
            ]);

            $alts = $this->ensureAlternatives((string)$request->input('q', ''), [], null, 8);
            return response()->json([
                'title'        => null,
                'answer'       => '',
                'issue_id'     => null,
                'alternatives' => $alts,
                'ask_feedback' => false,
            ], 200);
        }
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

        // Kalau user klik Tidak → kasih rekomendasi pertanyaan mirip
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
        $norm = fn(string $s) => mb_strtolower(trim($s));

        $alts = KIssue::similarQuestions($q, $min + 5);

        if ($excludeTitle) {
            $ex = $norm($excludeTitle);
            $alts = array_values(array_filter($alts, fn($t) => $norm($t) !== $ex));
        }

        if (count($alts) < $min) {
            $more = $this->suggestIssues($tokens ?: Nlp::tokens($q), $min + 5);
            if ($excludeTitle) {
                $ex = $norm($excludeTitle);
                $more = array_values(array_filter($more, fn($t) => $norm($t) !== $ex));
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
            $cand = [];
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
        $df = Nlp::df();
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
            $cand = [];
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
        $titles = [];

        if (!empty($tokens)) {
            $bool = collect($tokens)->map(fn ($t) => $t . '*')->implode(' ');
            $titles = KIssue::select('issue_name')
                ->whereRaw("MATCH(issue_name, solusion) AGAINST (? IN BOOLEAN MODE)", [$bool])
                ->orderByRaw("MATCH(issue_name, solusion) AGAINST (? IN BOOLEAN MODE) DESC", [$bool])
                ->limit($limit * 3)
                ->pluck('issue_name')->unique()->values()->all();
        }

        if (empty($titles) && !empty($tokens)) {
            $q = KIssue::query();
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
}
