<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Models\KIssue;
use App\Jobs\ReindexKIssue;
use App\Services\QdrantClient;
use App\Services\EmbedBuilder;

class KIssuesRebuild extends Command
{
    protected $signature   = 'kissues:rebuild 
                              {--chunk=400 : Batch reindex per n baris}
                              {--skip-migrate : Lewati artisan migrate}
                              {--sync : Jalankan job secara sinkron (cepat selesai, block CLI)}
                              {--dry-run : Hanya cek & tampilkan rencana aksi, tanpa mengubah apapun}';
    protected $description = 'Rebuild index MySQL & reindex semua KIssue ke Qdrant (judul-only embedding).';

    public function handle(): int
    {
        $dry   = (bool)$this->option('dry-run');
        $chunk = (int)$this->option('chunk');
        $sync  = (bool)$this->option('sync');

        // 0) optional: jalankan migrate dulu biar skema/index dari migration masuk
        if (!$this->option('skip-migrate')) {
            $this->info('> Running migrations…');
            if (!$dry) {
                Artisan::call('migrate', ['--force' => true]);
                $this->line(Artisan::output());
            } else {
                $this->line('(dry-run) skip execute migrate');
            }
        }

        // 1) pastikan INDEX ada (aman diulang — cek via information_schema)
        $this->info('> Ensuring MySQL indexes (FULLTEXT & btree)…');
        $this->ensureIndex('kissues', 'ft_issue_name',   'FULLTEXT', ['issue_name'], $dry);
        $this->ensureIndex('kissues', 'ft_title_body',   'FULLTEXT', ['issue_name','solusion'], $dry);
        $this->ensureIndex('kissues', 'idx_issue_name_norm', 'INDEX', ['issue_name_norm'], $dry);

        // 2) warmup embedder & ping Qdrant
        $this->info('> Warming up embedding & checking Qdrant…');
        if (!$dry) {
            app(EmbedBuilder::class)->embed('warmup');
            $ok = app(QdrantClient::class)->ping();
            if (!$ok) { $this->warn('Qdrant not reachable (/healthz failed). Reindex may fail.'); }
        }

        // 3) reindex semua KIssue (judul saja) → Qdrant
        $total = KIssue::count();
        $this->info("> Reindexing $total issues to Qdrant (title-only)…");
        if ($dry) {
            $this->line('(dry-run) skip enqueue jobs');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        KIssue::orderBy('id')->chunk($chunk, function ($rows) use ($bar, $sync) {
            foreach ($rows as $r) {
                if ($sync) {
                    dispatch_sync(new ReindexKIssue($r->id));
                } else {
                    dispatch(new ReindexKIssue($r->id));
                }
                $bar->advance();
            }
        });

        $bar->finish(); $this->newLine();
        $this->info('Done.');
        return self::SUCCESS;
    }

    /**
     * Pastikan index ada. Jika belum ada → buat.
     * $type: 'FULLTEXT' | 'INDEX'
     */
    protected function ensureIndex(string $table, string $indexName, string $type, array $cols, bool $dry): void
    {
        $exists = $this->indexExists($table, $indexName);
        if ($exists) {
            $this->line("  - $indexName already exists");
            return;
        }

        $colsSql = implode(',', array_map(fn($c)=>"`{$c}`", $cols));
        $sql = match ($type) {
            'FULLTEXT' => "ALTER TABLE `{$table}` ADD FULLTEXT `{$indexName}` ({$colsSql})",
            default    => "ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$colsSql})",
        };

        $this->line("  + create {$type} {$indexName} on {$table}({$colsSql})");
        if (!$dry) {
            DB::statement($sql);
        }
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $schema = DB::getDatabaseName();
        $row = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $schema)
            ->where('TABLE_NAME',   $table)
            ->where('INDEX_NAME',   $indexName)
            ->first();
        return (bool)$row;
    }
}