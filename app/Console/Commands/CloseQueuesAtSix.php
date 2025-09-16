<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Ticket;
use App\Models\Progress;
use Illuminate\Support\Facades\DB;

class CloseQueuesAtSix extends Command
{
    protected $signature = 'queues:close-1800';
    protected $description = 'Auto-stop semua antrean & step yang masih running pada 18:00 WIB';

    public function handle(): int
    {
        $today = now('Asia/Jakarta')->toDateString();

        DB::transaction(function () use ($today) {
            Progress::whereHas('ticket', fn($q) => $q->whereDate('ticket_date', $today))
                ->where('status', 'running')
                ->update(['status' => 'stopped', 'ended_at' => now()]);

            Ticket::where('ticket_date', $today)
                ->where('status', 'running')
                ->update(['status' => 'stopped']);
        });

        $this->info('Semua antrean/step aktif telah dihentikan.');
        return self::SUCCESS;
    }
}
