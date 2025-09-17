<?php

namespace App\Models;


use Illuminate\Support\Facades\Log;
use App\Support\Nlp;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KIssue extends Model
{



    protected $table = 'kissues';

    protected $fillable = [
        'layanan_id',
        'steplayanan_id', // FK ke k_steps
        'issue_name',
        'solusion',       // jika kolomnya 'solusion' tulis ini; kalau 'solution' -> ganti 'solution'
        'std_solution_time',
        'issue_name_norm','issue_tokens','aliases_norm',
    ];


     
    public static function clearNlpCaches(): void
    {
        Cache::forget('nlp_vocab_kissues');
        Cache::forget('nlp_df_kissues');
        Cache::forget('nlp_intent_lex');

    }

    protected static function booted(): void
    {
        static::created(fn () => self::clearNlpCaches());
        static::updated(fn () => self::clearNlpCaches());
        static::deleted(fn () => self::clearNlpCaches());

        // daftarkan event ini hanya kalau model pakai SoftDeletes
        if (in_array(SoftDeletes::class, class_uses_recursive(self::class), true)) {
            static::restored(fn () => self::clearNlpCaches());
            static::forceDeleted(fn () => self::clearNlpCaches());
        }
    }
    
    
    
    public function layanan()
    {
        return $this->belongsTo(KLayanan::class, 'layanan_id', 'id');
    }

    public function step()
    {
        // kolom FK di kissues adalah 'steplayanan_id'
        return $this->belongsTo(KStep::class, 'steplayanan_id', 'id');
    }

    public function kategori()
    {
        return $this->belongsTo(KategoriIssue::class, 'categoryissue_id', 'id');
    }

    protected $casts = [
        'issue_tokens' => 'array',
        'aliases_norm' => 'array',
    ];

    /* ========== Normalisasi umum (tanpa hardcode) ========== */
    public static function normalizePhrase(string $q): string
    {
        $q = Str::lower($q);
        $q = preg_replace('/[-_]/', ' ', $q);
        $q = preg_replace('/[^a-z0-9 ]+/u', ' ', $q);
        $q = preg_replace('/\s+/u', ' ', trim($q));
        return $q;
    }

    /** Hitung & isi kolom norm untuk record ini */
    public function applyNorms(): void
    {
        $this->issue_name_norm = self::normalizePhrase($this->issue_name ?? '');
        $toks = Nlp::tokens(($this->issue_name ?? '') . ' ' . ($this->solusion ?? ''));
        $toks = array_values(array_unique($toks)); sort($toks);
        $this->issue_tokens = $toks;
        if ($this->aliases_norm === null) $this->aliases_norm = [];
    }

    /* ===================== Smart Search ===================== */

    public static function smartSearch(string $term, int $limit = 8)
    {
        $tokens = Nlp::tokens($term);
        if (!$tokens) return collect();

        $needle  = Str::lower($term);
        $intent  = Nlp::intent($term);
        $expTok  = Nlp::expandByCooccur($tokens, 6);

        // boolean query
        $bool = collect(array_unique(array_merge($tokens, $expTok)))
                ->map(fn($t) => $t . '*')
                ->implode(' ');

        $phrase = self::normalizePhrase($term);
        if (Str::length($phrase) >= 5) $bool .= ' "' . $phrase . '"';

        if ($intent['isPay'] && !$intent['isTrouble']) {
            foreach ($intent['troubleLex'] as $neg) $bool .= ' -' . $neg . '*';
        }

        $pool = static::select('id','issue_name','solusion')
            ->selectRaw("MATCH(issue_name, solusion) AGAINST (? IN BOOLEAN MODE) AS ft_score", [$bool])
            ->whereRaw("MATCH(issue_name, solusion) AGAINST (? IN BOOLEAN MODE)", [$bool])
            ->orderByDesc('ft_score')
            ->limit(max($limit * 5, 40))
            ->get();

        if ($pool->isEmpty()) {
            $pool = static::tokenSearch($term, max($limit * 5, 40));
            if ($pool->isEmpty()) return collect();
        }

        // bobot adaptif
        $ft   = $pool->pluck('ft_score')->map(fn($x)=>(float)$x)->all();
        $mean = array_sum($ft) / max(count($ft), 1);
        $std  = self::stdDev($ft, $mean);
        $cv   = $mean > 0 ? $std / max($mean, 1e-9) : 0.0;

        $m   = count($tokens);
        $wFT = max(0.35, min(0.75, 0.5 + 0.25 * tanh($cv)));
        $wP  = max(0.18, min(0.35, 0.20 + max(0, 3 - $m)/3 * 0.15));
        $wJ  = max(0.10, 1 - $wFT - $wP);
        $norm= max($pool->max('ft_score') ?: 0, 1e-9);

        $qTokens = $tokens;
        $bigrams = self::bigrams($term);
        $howtoLex   = $intent['howtoLex'];
        $troubleLex = $intent['troubleLex'];

        $rescored = $pool->map(function ($row) use ($needle,$qTokens,$bigrams,$wFT,$wP,$wJ,$norm,$howtoLex,$troubleLex,$intent) {
            $title = Str::lower($row->issue_name ?? '');
            $hay   = Str::lower(($row->issue_name ?? '') . ' ' . ($row->solusion ?? ''));
            $rowTokens = Nlp::tokens($hay);

            $inter = count(array_intersect($qTokens, $rowTokens));
            $union = count(array_unique(array_merge($qTokens, $rowTokens))) ?: 1;
            $jacc  = $inter / $union;

            $ps = 0.0;
            if ($needle && Str::contains($hay, $needle)) $ps = 1.0;
            elseif ($bigrams) {
                $hit = 0; foreach ($bigrams as $bg) if (Str::contains($hay, $bg)) $hit++;
                $ps = $hit / count($bigrams);
            } elseif ($qTokens) {
                $ps = $inter ? min(1.0, $inter / max(2, count($qTokens))) : 0.0;
            }

            $ftNorm = ($row->ft_score ?? 0) / $norm;
            $score  = $wFT * $ftNorm + $wP * $ps + $wJ * $jacc;

            $hasHowto   = collect($howtoLex)->first(fn($w)=> Str::contains($title.' '.$hay, $w)) !== null;
            $hasTrouble = collect($troubleLex)->first(fn($w)=> Str::contains($title.' '.$hay, $w)) !== null;

            if ($intent['isPay'] && !$intent['isTrouble']) {
                if ($hasTrouble) $score -= 0.14;
                if ($hasHowto)   $score += 0.10;
            } elseif ($intent['isTrouble']) {
                if ($hasTrouble) $score += 0.08;
            }

            // 🔼 Boost: token query muncul di JUDUL
            $titleBoost = 0.0;
            foreach ($qTokens as $qt) {
                if (Str::contains($title, $qt)) $titleBoost += 0.04;
            }
            $score += min($titleBoost, 0.12);

            // 🔻 Penalti: judul memperkenalkan token spesifik (DF rendah) yang tak ada di query
            $df = Nlp::df();
            $titleToks = Nlp::tokens($title);
            $cand = [];
            foreach ($titleToks as $t) if (isset($df[$t])) $cand[$t] = $df[$t];
            asort($cand);
            $rareMiss = 0;
            foreach (array_slice(array_keys($cand), 0, 2) as $t) {
                if (!in_array($t, $qTokens, true)) $rareMiss++;
            }
            $score -= 0.06 * $rareMiss;

            $row->score = $score;
            return $row;
        })->sortByDesc('score')->values();

        return $rescored->take($limit);
    }

    protected static function bigrams(string $q): array
    {
        $t = preg_split('/\s+/', self::normalizePhrase($q), -1, PREG_SPLIT_NO_EMPTY);
        if (count($t) < 2) return [];
        $out = [];
        for ($i = 0; $i < count($t) - 1; $i++) $out[] = $t[$i] . ' ' . $t[$i + 1];
        return $out;
    }

    protected static function stdDev(array $xs, float $mean): float
    {
        $n = count($xs);
        if ($n <= 1) return 0.0;
        $acc = 0.0;
        foreach ($xs as $x) { $d = $x - $mean; $acc += $d * $d; }
        return sqrt($acc / ($n - 1));
    }

    public static function tokenSearch(string $term, int $limit = 5)
    {
        $tokens = Nlp::tokens($term);
        if (empty($tokens)) $tokens = [Str::lower(trim($term))];

        $q = static::query()->select(['id','issue_name','solusion']);

        $q->where(function ($qq) use ($tokens) {
            foreach ($tokens as $t) {
                $qq->where(function ($x) use ($t) {
                    $x->orWhere('issue_name', 'like', "%{$t}%")
                      ->orWhere('solusion',   'like', "%{$t}%");
                });
            }
        });

        $rows = $q->get()
            ->map(function ($row) use ($tokens) {
                $score = 0;
                foreach ($tokens as $t) {
                    $score += substr_count(Str::lower($row->issue_name), $t);
                    $score += substr_count(Str::lower($row->solusion),   $t);
                }
                $row->ft_score = $score;
                $row->score    = $score;
                return $row;
            })
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        return $rows;
    }

    public function progresses()
{
    return $this->belongsToMany(\App\Models\Progress::class, 'progress_issue', 'issue_id', 'progress_id')
                ->withTimestamps();
}


}
