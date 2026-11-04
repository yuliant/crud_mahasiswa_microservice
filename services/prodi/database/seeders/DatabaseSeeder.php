<?php

namespace Database\Seeders;

use App\Models\Prodi;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Prodi::firstOrCreate(['nama_prodi' => 'Sistem Informasi']);
        Prodi::firstOrCreate(['nama_prodi' => 'Teknik Informatika']);
    }
}
