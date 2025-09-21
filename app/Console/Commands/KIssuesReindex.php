<?php
// app/Console/Commands/KIssuesReindex.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KIssue;
use App\Jobs\ReindexKIssue;

class KIssuesReindex extends Command
{
    protected $signature = 'kissues:reindex {--chunk=400}';
    protected $description = 'Build embeddings & sync Qdrant for KIssue';

    public function handle(): int {
        $total = KIssue::count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();
        KIssue::orderBy('id')->chunk((int)$this->option('chunk'), function($rows) use ($bar) {
            foreach ($rows as $r) {
                dispatch_sync(new ReindexKIssue($r->id)); // bisa diganti queue
                $bar->advance();
            }
        });
        $bar->finish(); $this->newLine();
        $this->info('Done.');
        return self::SUCCESS;
    }
}
