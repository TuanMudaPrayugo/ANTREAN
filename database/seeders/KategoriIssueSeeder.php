<?php

namespace Database\Seeders;

use App\Models\KategoriIssue;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class KategoriIssueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        KategoriIssue::create([
            'category_name' => 'Dokumen'
        ]);

        KategoriIssue::create([
            'category_name' => 'Sistem'
        ]);

        KategoriIssue::create([
            'category_name' => 'Kendaraan'
        ]);

        KategoriIssue::create([
            'category_name' => 'Teknis'
        ]);

        KategoriIssue::create([
            'category_name' => 'Administrasi'
        ]);
    }
}
