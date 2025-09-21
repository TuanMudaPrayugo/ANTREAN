<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class QdrantDiag extends Command
{
    protected $signature = 'qdrant:diag';
    protected $description = 'Diagnose Qdrant connectivity from PHP';

    public function handle(): int
    {
        $host = env('QDRANT_HOST', '127.0.0.1');
        $port = env('QDRANT_PORT', '6333');
        $url  = "http://{$host}:{$port}/healthz";

        $this->info("GET $url (IPv4 forced, proxy disabled) ...");

        try {
            $res = Http::withOptions([
                    'force_ip_resolve' => 'v4', // paksa IPv4
                    'proxy'            => null, // matikan proxy apapun
                ])
                ->timeout((int)env('QDRANT_TIMEOUT', 8))
                ->get($url);

            $this->line('Status: '.$res->status());
            $this->line('Body  : '.$res->body());
            return $res->ok() ? self::SUCCESS : self::FAILURE;

        } catch (\Throwable $e) {
            $this->error('EXCEPTION: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
