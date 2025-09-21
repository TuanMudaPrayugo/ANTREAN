<?php

namespace App\Jobs;

use App\Models\KIssue;
use App\Services\EmbedBuilder;
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
    public int $tries   = 3;

    public function __construct(public int $id) {}

    public function handle(EmbedBuilder $emb, QdrantClient $qd): void
    {
        // Ambil minimal kolom yang perlu saja → lebih hemat
        $row = KIssue::query()
            ->select('id', 'issue_name', 'solusion', 'updated_at')
            ->find($this->id);

        if (!$row) return;

        // ⤵️ Embed JUDUL saja (lebih stabil & murah)
        $title = trim((string) $row->issue_name);
        if ($title === '') return;

        $vec = $emb->embed(mb_substr($title, 0, 256));
        if (empty($vec)) return;

        $dim = count($vec);

        // Pastikan collection tersedia
        $qd->ensureCollection($dim, env('QDRANT_DISTANCE', 'Cosine'));

        // (Opsional) simpan embedding ke DB untuk audit/caching internal
        $row->emb            = EmbedBuilder::packFloat32(array_map('floatval', $vec));
        $row->emb_dim        = $dim;
        $row->emb_model      = env('EMBED_MODEL');
        $row->last_indexed_at = now();
        $row->save();

        // Upsert ke Qdrant (API expects array of "points")
        $qd->upsert([[
            'id'      => (int) $row->id,
            'vector'  => array_map('floatval', $vec),
            'payload' => [
                'issue_name' => (string) $row->issue_name,
                'solusion'   => (string) $row->solusion,
                'updated_at' => (string) $row->updated_at,
            ],
        ]]);
    }
}
