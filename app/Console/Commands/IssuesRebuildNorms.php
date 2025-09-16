<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KIssue;

class IssuesRebuildNorms extends Command
{
    protected $signature = 'issues:rebuild-norms {--chunk=500}';
    protected $description = 'Rebuild issue_name_norm & issue_tokens for KIssue';

    public function handle(): int
    {
        $chunk = (int) $this->option('chunk');
        $total = KIssue::count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        KIssue::chunk($chunk, function ($rows) use ($bar) {
            foreach ($rows as $r) {
                $r->applyNorms();
                $r->save();
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done rebuilding norms.');
        return self::SUCCESS;
    }
}
