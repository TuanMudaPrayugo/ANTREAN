<?php

namespace Database\Seeders;

use App\Models\KLayanan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class KLayananSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        KLayanan::create([
            'service_name' => 'Ganti STNK',
            'keterangan'=> 'Layanan Penggantian STNK',
            'status_layanan' => true
        ]);
        KLayanan::create([
            'service_name' => 'Balik Nama',
            'keterangan'=> 'Layanan Balik Nama Kendaraan Bermotor',
            'status_layanan' => true
        ]);

        KLayanan::create([
            'service_name' => 'Mutasi Masuk',
            'keterangan'=> 'Layanan Mutasi Kendaraan Dari Luar Daerah',
            'status_layanan' => true
        ]);
    }
}
