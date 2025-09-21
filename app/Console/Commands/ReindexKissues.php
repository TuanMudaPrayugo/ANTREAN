<?php

namespace App\Jobs;

use App\Models\KIssue;
use App\Services\Embedder;
use App\Services\QdrantClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReindexKIssue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries   = 3;   // retry ringan

    public function __construct(public int $id) {}

    public function handle(Embedder $embedder, QdrantClient $qdrant): void
    {
        $row = KIssue::find($this->id);
        if (!$row) return;

        // Embed JUDUL saja
        $title = trim((string)$row->issue_name);
        if ($title === '') return;

        try {
            $vec = $embedder->embed(mb_substr($title, 0, 256));
            if (empty($vec)) return;

            // Siapkan collection sekali jalan
            $qdrant->ensureCollection(count($vec), env('QDRANT_DISTANCE', 'Cosine'));

            // Upsert ke Qdrant
            $qdrant->upsert([[
                'id'     => (int)$row->id,
                'vector' => array_map('floatval', $vec),
                'payload'=> [
                    'issue_name' => (string)$row->issue_name,
                    'solusion'   => (string)$row->solusion,
                    'updated_at' => (string)$row->updated_at,
                ],
            ]]);

        } catch (\Throwable $e) {
            // biar job bisa retry, lempar lagi
            throw $e;
        }
    }
}
