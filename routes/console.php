<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;   // <-- tambahkan ini


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jadwal auto-stop antrean jam 18:00 WIB
// Schedule::command('queues:close-1800')
//     ->dailyAt('18:00')
//     ->timezone('Asia/Jakarta');

    // dailyAt('18:00')