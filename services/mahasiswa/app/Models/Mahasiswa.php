<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mahasiswa extends Model
{
    protected $table = 'mahasiswa';
    public $timestamps = false;
    protected $fillable = ['id_prodi', 'nama', 'nim'];

    protected function casts(): array
    {
        return ['id_prodi' => 'integer'];
    }
}
