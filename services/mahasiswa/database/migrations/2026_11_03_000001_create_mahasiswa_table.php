<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mahasiswa', function (Blueprint $table) {
            $table->id();
            // Reference to data owned by Service Prodi, with no cross-database foreign key.
            $table->unsignedBigInteger('id_prodi')->index();
            $table->string('nama', 100);
            $table->string('nim', 30)->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mahasiswa');
    }
};
