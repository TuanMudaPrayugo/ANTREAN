<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class Embedder
{
    private function base(): string
    {
        return 'http://'.env('EMBED_HOST', '127.0.0.1').':'.env('EMBED_PORT', 11434);
    }

    private function http(int $timeout = null)
    {
        return Http::withOptions([
                'force_ip_resolve' => 'v4',
                'proxy'            => null,
            ])
            ->retry(2, 250)
            ->timeout($timeout ?? (int)env('EMBED_TIMEOUT', 12));
    }

    public function embed(string $text): array
    {
        $key = 'emb:'.md5(mb_strtolower(trim($text)));
        return Cache::remember($key, 120, function () use ($text) {
        $res = $this->http()->post($this->base().'/api/embeddings', [
            'model' => env('EMBED_MODEL', 'nomic-embed-text'),
            'input' => $text,
        ])->throw()->json();

        return $res['embedding'] ?? ($res['data'][0]['embedding'] ?? []);
        });
    }

    /** @return array<int,array<float>> */
    public function embedBatch(array $texts): array
    {
        if (empty($texts)) return [];

        $res = $this->http(30)->post($this->base().'/api/embeddings', [
            'model' => env('EMBED_MODEL', 'nomic-embed-text'),
            'input' => array_values($texts),
        ])->throw()->json();

        if (isset($res['data']) && is_array($res['data'])) {
            return array_map(fn($i)=>$i['embedding'] ?? [], $res['data']);
        }
        if (isset($res['embedding'])) return [ $res['embedding'] ];
        return [];
    }

    public function warmup(): void
    {
        try { $this->embed('warmup'); } catch (\Throwable) {}
    }

    
}
