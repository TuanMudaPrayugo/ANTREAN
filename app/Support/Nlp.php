<?php

namespace App\Support;

use App\Models\KIssue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Nlp
{
    /* ============================================================
     *  Stemmer & Stopword (pakai Sastrawi jika tersedia)
     * ============================================================ */
    protected static $stemmer;
    protected static $stopRemover;

    protected static function stemmer() {
        if (self::$stemmer) return self::$stemmer;
        if (class_exists(\Sastrawi\Stemmer\StemmerFactory::class)) {
            $factory = new \Sastrawi\Stemmer\StemmerFactory();
            self::$stemmer = $factory->createStemmer();
        }
        return self::$stemmer;
    }

    protected static function stopRemover() {
        if (self::$stopRemover) return self::$stopRemover;
        if (class_exists(\Sastrawi\StopWordRemover\StopWordRemoverFactory::class)) {
            $factory = new \Sastrawi\StopWordRemover\StopWordRemoverFactory();
            self::$stopRemover = $factory->createStopWordRemover();
        }
        return self::$stopRemover;
    }

    /* ============================================================
     *  Korpus: Vocabulary (token unik) & DF (document frequency)
     * ============================================================ */

    /** Semua token unik (sudah distem), sumber: issue_name + solusion */
    public static function vocab(): array
    {
        return Cache::remember('nlp_vocab_kissues', 3600, function () {
            $stemmer = self::stemmer();

            $texts = KIssue::query()->select('issue_name','solusion')->get()
                ->flatMap(fn($r)=>[$r->issue_name, $r->solusion])
                ->implode(' ');

            $clean = Str::lower(preg_replace('/[^a-z0-9 ]+/u', ' ', $texts));
            $raw   = array_unique(preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY));
            if ($stemmer) $raw = array_map(fn($w) => $stemmer->stem($w), $raw);

            return array_values(array_filter($raw, fn($w)=>mb_strlen($w) >= 2));
        });
    }

    /** DF: token -> jumlah dokumen yang memuat token */
    public static function df(): array
    {
        return Cache::remember('nlp_df_kissues', 3600, function () {
            $stemmer = self::stemmer();
            $stopper = self::stopRemover();

            $rows = KIssue::query()->select('issue_name','solusion')->get();
            $df = [];

            foreach ($rows as $r) {
                $text = Str::lower(($r->issue_name ?? '') . ' ' . ($r->solusion ?? ''));
                $text = preg_replace('/[-_]/',' ',$text);
                $text = preg_replace('/[^a-z0-9 ]+/u',' ',$text);
                $text = preg_replace('/\s+/u',' ',trim($text));
                if ($stopper) $text = $stopper->remove($text);

                $parts  = array_unique(preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY));
                $tokens = array_map(fn($w) => $stemmer ? $stemmer->stem($w) : $w, $parts);

                foreach (array_unique($tokens) as $t) {
                    if (mb_strlen($t) < 2) continue;
                    $df[$t] = ($df[$t] ?? 0) + 1;
                }
            }

            arsort($df);
            return $df;
        });
    }

    /* ============================================================
     *  Alias Dinamis dari Korpus (tanpa daftar manual)
     * ============================================================ */

    /**
     * Map token user → token kanonik dari vocab memakai:
     * 1) Prefix match + dominasi DF
     * 2) Fallback Levenshtein (toleransi typo kecil)
     */
    protected static function dynamicAliasFromCorpus(string $w): ?string
    {
        $w = Str::lower($w);
        if (mb_strlen($w) < 2) return null;

        static $V = null, $DF = null;
        if ($V === null) {
            $V  = array_flip(self::vocab()); // vocab -> index
            $DF = self::df();                // token -> df
        }

        // Sudah kanonik
        if (isset($V[$w])) return $w;

        // (1) Prefix match
        $cands = [];
        foreach ($V as $voc => $idx) {
            if (Str::startsWith($voc, $w)) {
                $cands[$voc] = $DF[$voc] ?? 1;
            }
        }

        if (!empty($cands)) {
            arsort($cands);
            $keys = array_keys($cands);
            $top  = $keys[0];

            // hanya 1 kandidat → langsung
            if (count($keys) === 1) return $top;

            // ada >=2 kandidat → butuh dominasi DF agar aman
            $secV = $cands[$keys[1]] ?? 0;
            if ($secV === 0 || $cands[$top] >= 2 * $secV) {
                return $top;
            }
            // jika tidak dominan, teruskan ke fallback di bawah
        }

        // (2) Fallback Levenshtein (untuk typo)
        $best = null; $bestDist = 10; $lenT = mb_strlen($w);
        foreach ($V as $voc => $idx) {
            $lenV = mb_strlen($voc);
            if (abs($lenV - $lenT) > 2) continue;
            $d = levenshtein($w, $voc);
            if ($d < $bestDist) { $bestDist = $d; $best = $voc; }
            if ($bestDist === 0) break;
        }
        if ($best !== null) {
            if (($lenT <= 7 && $bestDist <= 1) || ($lenT > 7 && $bestDist <= 2)) {
                return $best;
            }
        }

        return null;
    }

    /* ============================================================
     *  Tokenizer
     * ============================================================ */

    /** Tokenizer tanpa alias (dipakai untuk ekstraksi ringan) */
    public static function tokensNoAlias(string $text): array
    {
        $stemmer = self::stemmer();
        $stopper = self::stopRemover();

        $text = Str::lower($text);
        $text = preg_replace('/[-_]/', ' ', $text);
        $text = preg_replace('/[^a-z0-9 ]+/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', trim($text));
        if ($stopper) $text = $stopper->remove($text);

        $parts = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $tokens = array_map(function ($w) use ($stemmer) {
            if ($stemmer) $w = $stemmer->stem($w);
            return $w;
        }, $parts);

        return array_values(array_filter($tokens, fn($w)=>ctype_digit($w) || mb_strlen($w) >= 2));
    }

    /** Tokenizer utama: normalisasi + alias dinamis + koreksi ke vocab */
    public static function tokens(string $text): array
    {
        $stemmer = self::stemmer();
        $stopper = self::stopRemover();

        // Normalisasi umum
        $text = Str::lower($text);
        $text = preg_replace('/[-_]/', ' ', $text);
        $text = preg_replace('/[^a-z0-9 ]+/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', trim($text));
        if ($stopper) $text = $stopper->remove($text);

        $parts = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Alias dinamis (tanpa daftar manual)
        $parts = array_map(function ($w) {
            return self::dynamicAliasFromCorpus($w) ?? $w;
        }, $parts);

        // Stemming + koreksi ke vocab
        $tokens = array_map(function ($w) use ($stemmer) {
            if ($stemmer) $w = $stemmer->stem($w);
            return self::correctToVocab($w);
        }, $parts);

        return array_values(array_filter($tokens, fn($w)=>ctype_digit($w) || mb_strlen($w) >= 2));
    }

    /* ============================================================
     *  Koreksi Token → Vocab
     * ============================================================ */

    protected static function correctToVocab(string $token): string
    {
        $token = Str::lower($token);
        $vocab = self::vocab();
        if (in_array($token, $vocab, true)) return $token;

        // (1) Jaro–Winkler
        $best = $token; $bestScore = 0.0;
        foreach ($vocab as $v) {
            $s = self::jaroWinkler($token, $v);
            if ($s > $bestScore) { $bestScore = $s; $best = $v; }
            if ($bestScore >= 0.965) break;
        }
        if ($bestScore >= 0.92) return $best;
        if (mb_strlen($token) <= 4 && $bestScore >= 0.88) return $best;

        // (2) Prefix unique / DF-dominant (SAFE)
        if (mb_strlen($token) >= 3) {
            $df = self::df();
            $cands = [];
            foreach ($vocab as $v) {
                if (Str::startsWith($v, $token)) {
                    $cands[$v] = $df[$v] ?? 1;
                }
            }
            if (count($cands) === 1) {
                return array_key_first($cands);
            }
            if (count($cands) >= 2) {
                arsort($cands);
                $keys = array_keys($cands);
                $top  = $keys[0];
                $sec  = $cands[$keys[1]] ?? 0;
                if ($sec === 0 || $cands[$top] >= 2 * $sec) return $top;
            }
        }

        // (3) Levenshtein fallback (untuk typo)
        if (mb_strlen($token) >= 5) {
            $best = $token; $bestDist = 10; $lenT = mb_strlen($token);
            foreach ($vocab as $v) {
                $lenV = mb_strlen($v);
                if (abs($lenV - $lenT) > 2) continue;
                $d = levenshtein($token, $v);
                if ($d < $bestDist) { $bestDist = $d; $best = $v; }
                if ($bestDist === 0) break;
            }
            if (($lenT <= 7 && $bestDist <= 1) || ($lenT > 7 && $bestDist <= 2)) return $best;
        }

        return $token;
    }

    protected static function jaroWinkler(string $s1, string $s2): float
    {
        $jaro = function ($s, $a) {
            $s_len = strlen($s); $a_len = strlen($a);
            if ($s_len === 0 && $a_len === 0) return 1.0;
            $match_distance = (int) floor(max($s_len, $a_len) / 2) - 1;

            $s_matches = array_fill(0, $s_len, false);
            $a_matches = array_fill(0, $a_len, false);
            $matches = 0; $transpositions = 0;

            for ($i = 0; $i < $s_len; $i++) {
                $start = max(0, $i - $match_distance);
                $end   = min($i + $match_distance + 1, $a_len);
                for ($j = $start; $j < $end; $j++) {
                    if ($a_matches[$j]) continue;
                    if ($s[$i] !== $a[$j]) continue;
                    $s_matches[$i] = true;
                    $a_matches[$j] = true;
                    $matches++;
                    break;
                }
            }
            if ($matches === 0) return 0.0;

            $k = 0;
            for ($i = 0; $i < $s_len; $i++) {
                if (!$s_matches[$i]) continue;
                while (!$a_matches[$k]) $k++;
                if ($s[$i] !== $a[$k]) $transpositions++;
                $k++;
            }
            $m = $matches;
            return (($m / $s_len) + ($m / $a_len) + (($m - $transpositions/2) / $m)) / 3.0;
        };

        $j = $jaro($s1, $s2);
        $prefix = 0; $maxPrefix = 4;
        $n = min($maxPrefix, min(strlen($s1), strlen($s2)));
        for ($i=0; $i<$n; $i++) {
            if ($s1[$i] === $s2[$i]) $prefix++;
            else break;
        }
        return $j + ($prefix * 0.1 * (1 - $j));
    }

    /* ============================================================
     *  Anchor & Co-occur (untuk ekspansi token/intent)
     * ============================================================ */

    /** Pilih token jangkar paling informatif (DF terkecil) */
    public static function pickAnchor(array $tokens): ?string
    {
        $df = self::df();
        $inVocab = array_values(array_filter($tokens, fn($t) => isset($df[$t])));
        if (empty($inVocab)) return $tokens[0] ?? null;
        usort($inVocab, fn($a,$b) => ($df[$a] <=> $df[$b]));
        return $inVocab[0];
    }

    /** Co-occur sederhana (untuk ekspansi token/intent) */
    public static function cooccurFor(string $seed, int $top = 10, int $min_df = 2): array
    {
        $seed = Str::lower($seed);
        if (mb_strlen($seed) < 2) return [];

        return Cache::remember("nlp_cooc_seed_{$seed}_{$top}", 3600, function () use ($seed, $top, $min_df) {
            $stemmer = self::stemmer();
            $stopper = self::stopRemover();

            $N = 0; $co = []; $df = self::df();

            KIssue::select('issue_name','solusion')->chunk(400, function ($rows) use (&$N,&$co,$seed,$stemmer,$stopper) {
                foreach ($rows as $r) {
                    $text = Str::lower(($r->issue_name ?? '') . ' ' . ($r->solusion ?? ''));
                    if (!Str::contains($text, $seed)) continue;
                    $N++;

                    $text = preg_replace('/[-_]/',' ',$text);
                    $text = preg_replace('/[^a-z0-9 ]+/u',' ',$text);
                    $text = preg_replace('/\s+/u',' ',trim($text));
                    if ($stopper) $text = $stopper->remove($text);

                    $parts  = array_unique(preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY));
                    $tokens = array_map(fn($w) => $stemmer ? $stemmer->stem($w) : $w, $parts);

                    foreach ($tokens as $t) {
                        if (mb_strlen($t) < 2 || $t === $seed) continue;
                        $co[$t] = ($co[$t] ?? 0) + 1;
                    }
                }
            });

            if ($N === 0) return [];
            $Nall = max(array_sum($df), 1);

            $scored = [];
            foreach ($co as $t => $cxy) {
                $dft = $df[$t] ?? 1;
                if ($dft < $min_df) continue;
                $pmi = log(max(1, $cxy) * $Nall / max(1, $N * $dft));
                $scored[$t] = $pmi;
            }
            arsort($scored);
            return array_slice(array_keys($scored), 0, $top);
        });
    }

    public static function expandByCooccur(array $tokens, int $perToken = 5): array
    {
        $out = [];
        foreach ($tokens as $t) foreach (self::cooccurFor($t, $perToken) as $e) $out[$e] = true;
        return array_values(array_diff(array_keys($out), $tokens));
    }

    /* ============================================================
     *  LEXICON INTENT DINAMIS + INTENT()
     * ============================================================ */

    /** Bangun leksikon intent secara dinamis dari korpus */
    protected static function learnIntentLexicons(): array
    {
        return Cache::remember('nlp_intent_lex', 3600, function () {
            // helper: ambil co-occur dari beberapa seed
            $expand = function (array $seeds, int $top = 25): array {
                $bag = [];
                foreach ($seeds as $s) {
                    foreach (self::cooccurFor($s, $top) as $w) $bag[$w] = true;
                }
                foreach ($seeds as $s) $bag[$s] = true; // sertakan seed
                return array_keys($bag);
            };

            // 1) HOW-TO → token yang lebih sering di SOLUSION daripada ISSUE_NAME
            $howtoExtra = self::topDifferentialTerms(['solusion' => 1.0, 'issue_name' => -0.8], 40);
            $howtoLex = array_values(array_unique(array_merge(
                $expand(['cara','langkah']), // seed kecil
                $howtoExtra
            )));

            // 2) TROUBLE → sekitar error/gagal/bug
            $troubleLex = $expand(['error','gagal','bug','trouble','tidak']);

            // 3) PAY → sekitar bayar/tagihan/invoice
            $payLex = $expand(['bayar','tagihan','invoice','payment','refund','saldo']);

            // bersihkan: minimal ada di DF + panjang >= 3
            $df = self::df();
            $clean = function(array $arr) use ($df) {
                $arr = array_map('strval', $arr);
                $arr = array_filter($arr, fn($w) => isset($df[$w]) && mb_strlen($w) >= 3);
                return array_values(array_unique($arr));
            };

            return [
                'howtoLex'   => $clean($howtoLex),
                'troubleLex' => $clean($troubleLex),
                'payLex'     => $clean($payLex),
            ];
        });
    }

    /**
     * Ambil token yang cenderung "prosedural":
     * skor positif jika lebih sering di kolom solusion (dibanding issue_name).
     */
    protected static function topDifferentialTerms(array $colWeights, int $top = 40): array
    {
        $scores = [];
        KIssue::select('issue_name','solusion')->chunk(400, function ($rows) use (&$scores, $colWeights) {
            foreach ($rows as $r) {
                $txt = [
                    'issue_name' => $r->issue_name ?? '',
                    'solusion'   => $r->solusion   ?? '',
                ];
                foreach ($colWeights as $col => $w) {
                    $tokens = self::tokensNoAlias($txt[$col]);
                    foreach (array_unique($tokens) as $t) {
                        if (mb_strlen($t) < 3) continue;
                        $scores[$t] = ($scores[$t] ?? 0) + $w;
                    }
                }
            }
        });

        $pos = array_filter($scores, fn($v) => $v > 0);
        arsort($pos);
        return array_slice(array_keys($pos), 0, $top);
    }

    /** Deteksi intent query memakai leksikon dinamis */
    public static function intent(string $q): array
    {
        $q = Str::lower($q);
        $lex = self::learnIntentLexicons();

        $contains = function (array $lexicons) use ($q) {
            foreach ($lexicons as $w) {
                if (Str::contains($q, $w)) return true;
            }
            return false;
        };

        return [
            'isPay'       => $contains($lex['payLex']),
            'isTrouble'   => $contains($lex['troubleLex']),
            'howtoLex'    => $lex['howtoLex'],    // dipakai untuk boosting di smartSearch
            'troubleLex'  => $lex['troubleLex'],  // dipakai untuk boosting di smartSearch
        ];
    }
}
