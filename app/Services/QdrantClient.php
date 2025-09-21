<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class QdrantClient
{
    private string $host;
    private string $port;
    private string $collection;

    public function __construct(?string $host = null, ?string $port = null, ?string $collection = null)
    {
        $this->host       = $host       ?? env('QDRANT_HOST', '127.0.0.1');
        $this->port       = $port       ?? env('QDRANT_PORT', '6333');
        $this->collection = $collection ?? env('QDRANT_COLLECTION', 'kissues');
    }

    private function base(): string
    {
        return "http://{$this->host}:{$this->port}";
    }

    private function http(int $timeout = null)
    {
        return Http::withOptions([
                'force_ip_resolve' => 'v4',     // penting di Windows
                'proxy'            => null,     // disable proxy (kompatibel semua versi)
            ])
            ->retry((int)env('QDRANT_RETRIES', 2), 250)
            ->timeout($timeout ?? (int)env('QDRANT_TIMEOUT', 8));
    }

    public function ping(): bool
    {
        try {
            return $this->http(6)->get($this->base().'/healthz')->ok();
        } catch (\Throwable) {
            return false;
        }
    }

    public function ensureCollection(int $dim, ?string $distance = null): void
    {
        $distance = $distance ?? env('QDRANT_DISTANCE', 'Cosine');
        $url = $this->base()."/collections/{$this->collection}";

        $exists = $this->http(6)->get($url);
        if ($exists->ok()) return;

        $this->http(15)->put($url, [
            "vectors" => ["size" => $dim, "distance" => $distance, "on_disk" => false],
            "hnsw_config" => ["m" => 16, "ef_construct" => 128, "full_scan_threshold" => 20000],
            "optimizers_config" => ["memmap_threshold" => 200000],
            "quantization_config" => ["scalar" => ["type" => "int8", "always_ram" => true]],
        ])->throw();
    }

    /** @param array<array{id:int,vector:array<float>,payload:array}> $points */
    public function upsert(array $points): void
    {
        $this->http()->post($this->base()."/collections/{$this->collection}/points", [
            "points" => $points
        ])->throw();
    }

    public function search(array $vector, int $topK = 10): array
    {
        $res = $this->http()->post($this->base()."/collections/{$this->collection}/points/search", [
            "vector"       => array_map('floatval', $vector),
            "limit"        => $topK,
            "with_payload" => true,
        ])->throw()->json();

        return $res['result'] ?? [];
    }
}
