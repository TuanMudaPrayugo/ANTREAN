<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class EmbedBuilder
{
    public function __construct(
        private ?string $endpoint = null,
        private ?string $model = null
    ){
        $this->endpoint = $this->endpoint ?? env('EMBED_ENDPOINT','http://127.0.0.1:11434/api/embeddings');
        $this->model    = $this->model    ?? env('EMBED_MODEL','nomic-embed-text');
    }

    public function embed(string $text): array {
        $text = trim($text);
        if ($text === '') return [];
        $res = Http::timeout(15)->post($this->endpoint, [
            'model'=>$this->model, 'prompt'=>$text
        ])->throw()->json();

        // Kompatibel berbagai server
        $vec = $res['embedding'] ?? ($res['data'][0]['embedding'] ?? null);
        if (!is_array($vec)) return [];
        return array_map('floatval', $vec);
    }

    /* pack/unpack float32 little-endian */
    public static function packFloat32(array $floats): string {
        $bin=''; foreach ($floats as $f) $bin .= pack('g', (float)$f);
        return $bin;
    }
    public static function unpackFloat32(?string $bin): array {
        if (!$bin) return [];
        $n = intdiv(strlen($bin),4); $out=[];
        for($i=0;$i<$n;$i++) $out[] = unpack('g', substr($bin,$i*4,4))[1];
        return $out;
    }
}
