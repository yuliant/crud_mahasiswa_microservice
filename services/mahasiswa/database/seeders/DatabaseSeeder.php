<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Tambahkan mahasiswa melalui frontend/API setelah Service Prodi berjalan.');
    }
}
