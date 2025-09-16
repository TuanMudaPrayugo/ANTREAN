<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LlmService
{
    /**
     * Merapikan (paraphrase ringan) jawaban dari DB agar enak dibaca.
     * TIDAK menambah/mengurangi fakta. Jika hasil dianggap tidak aman, fallback ke rawAnswer.
     */
    public function polish(string $question, string $rawAnswer): string
    {
        // OFF switch
        if (!filter_var(env('USE_LLM', true), FILTER_VALIDATE_BOOL)) {
            return $rawAnswer;
        }

        $raw = trim($rawAnswer);
        if ($raw === '' || mb_strlen($raw) < 8) return $rawAnswer;

        // Untuk jawaban singkat, jangan dipoles (risiko berubah makna)
        if (mb_strlen($raw) <= 160) {
            return $rawAnswer;
        }

        $endpoint = env('LLM_ENDPOINT', 'http://127.0.0.1:11434/api/chat');
        $model    = env('LLM_MODEL', 'qwen2:1.5b-instruct');

        // Instruksi ketat: keluarkan hanya di antara <out>...</out>
        $system = implode("\n", [
            "Kamu asisten CS.",
            "Tugas: rapikan kalimat pada JAWABAN MENTAH agar lebih natural dan mudah dibaca.",
            "ATURAN:",
            "- Jangan menambah/menghapus/mengubah fakta.",
            "- Jangan tambahkan heading, catatan, sumber, URL, nomor telepon, email, atau 'lihat juga'.",
            "- Boleh bullet/penomoran JIKA dan HANYA JIKA ada langkah pada teks mentah.",
            "- KELUARKAN hanya hasil akhir di antara tag <out>...</out> tanpa teks lain."
        ]);

        $user = <<<TXT
JAWABAN MENTAH (sumber kebenaran):
{$raw}

Instruksi: Rapikan gaya bahasa seperlunya tanpa mengubah isi.
TXT;

        try {
            $res = Http::timeout(30)->post($endpoint, [
                'model'    => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
                'options'  => ['temperature' => 0.0, 'top_p' => 0.0],
                'stream'   => false,
            ])->json();

            // kompatibel dengan beberapa server (ollama/compat/openai)
            $content = $res['message']['content'] ?? ($res['choices'][0]['message']['content'] ?? '');
            $out     = (string) $content;

            $nice = $this->extractBody($out);

            // Validasi ketat anti-halu
            if (!$this->isSafePolish($raw, $nice)) {
                return $rawAnswer;
            }

            return $nice !== '' ? $nice : $rawAnswer;

        } catch (\Throwable $e) {
            return $rawAnswer;
        }
    }

    /* ================= Helpers ================= */

    /** Ambil isi di <out>...</out> & bersihkan label yang tak perlu */
    protected function extractBody(string $txt): string
    {
        if (preg_match('/<out>(.*)<\/out>/is', $txt, $m)) {
            $txt = $m[1];
        }

        // buang label yang sering muncul
        $lines = preg_split('/\r?\n/', trim($txt));
        $lines = array_values(array_filter($lines, function ($ln) {
            $l = mb_strtolower(trim($ln));
            if ($l === '') return false;
            if (str_starts_with($l, 'pertanyaan:')) return false;
            if (str_starts_with($l, 'jawaban mentah')) return false;
            if (str_starts_with($l, 'lihat juga')) return false;
            return true;
        }));

        $body = trim(implode("\n", $lines));
        $body = preg_replace('/[ \t]+\n/', "\n", $body);
        $body = preg_replace('/\n{3,}/', "\n\n", $body);

        return (string) $body;
    }

    /** Cek tidak melenceng (URL/email/telp baru, angka/negasi berubah, overlap cukup) */
    protected function isSafePolish(string $raw, string $nice): bool
    {
        $r = mb_strtolower($raw);
        $n = mb_strtolower($nice);

        // 1) URL/email/telp baru → tidak aman
        $rxUrl   = '/https?:\/\/[^\s]+/i';
        $rxEmail = '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i';
        $rxPhone = '/\b(\+?\d[\d\s\-]{7,})\b/';

        if ($this->hasNew($rxUrl, $r, $n))   return false;
        if ($this->hasNew($rxEmail, $r, $n)) return false;
        if ($this->hasNew($rxPhone, $r, $n)) return false;

        // 2) ANGKA: digit & angka-kata di output harus subset dari raw
        $numR = $this->numbersIn($r);
        $numN = $this->numbersIn($n);
        if (array_diff($numN, $numR)) return false;

        // 3) NEGASI: tidak/bukan/tanpa tidak boleh muncul/hilang
        $neg = ['tidak','bukan','tanpa','jangan','dilarang'];
        foreach ($neg as $w) {
            $inR = str_contains($r, $w);
            $inN = str_contains($n, $w);
            if ($inR !== $inN) return false;
        }

        // 4) Panjang wajar
        $lenR = max(1, mb_strlen($raw));
        $lenN = mb_strlen($nice);
        if ($lenN < 0.5 * $lenR || $lenN > 2.2 * $lenR) return false;

        // 5) Overlap token — lebih ketat untuk teks pendek
        $tok = function ($s) {
            $s = preg_replace('/[^a-z0-9 ]+/iu', ' ', mb_strtolower($s));
            $s = preg_replace('/\s+/u', ' ', trim($s));
            return array_values(array_filter(explode(' ', $s), fn($w)=>mb_strlen($w)>=3));
        };
        $tr = array_unique($tok($raw));
        $tn = array_unique($tok($nice));
        if (empty($tr) || empty($tn)) return false;

        $hit = 0; $setN = array_flip($tn);
        foreach ($tr as $w) if (isset($setN[$w])) $hit++;
        $overlap = $hit / max(1, count($tr));

        // Ambang dinamis: raw ≤ 300 char → 0.75; lainnya 0.55
        $need = (mb_strlen($raw) <= 300) ? 0.75 : 0.55;
        return $overlap >= $need;
    }

    /** Ada entitas baru di nice tapi tidak ada di raw? */
    protected function hasNew(string $regex, string $raw, string $nice): bool
    {
        preg_match_all($regex, $raw,  $mr);
        preg_match_all($regex, $nice, $mn);
        $R = array_map('strtolower', array_unique($mr[0] ?? []));
        $N = array_map('strtolower', array_unique($mn[0] ?? []));
        return (bool) array_diff($N, $R);
    }

    /** Ambil angka digit & angka kata (ID) sebagai angka normalisasi */
    protected function numbersIn(string $s): array
    {
        $out = [];

        // digit (123)
        if (preg_match_all('/\b\d+\b/u', $s, $m)) {
            foreach ($m[0] as $d) $out[] = (int) $d;
        }

        // angka kata (dasar)
        $map = [
            'nol'=>0,'satu'=>1,'sebuah'=>1,'dua'=>2,'tiga'=>3,'empat'=>4,'lima'=>5,
            'enam'=>6,'tujuh'=>7,'delapan'=>8,'sembilan'=>9,'sepuluh'=>10
        ];
        foreach ($map as $w => $v) {
            if (preg_match('/\b'.$w.'\b/u', $s)) $out[] = $v;
        }

        sort($out);
        return array_values(array_unique($out));
    }
}
