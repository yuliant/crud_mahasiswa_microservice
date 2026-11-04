<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Mahasiswa */
class MahasiswaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'id_prodi' => $this->id_prodi,
            'nama' => $this->nama,
            'nim' => $this->nim,
        ];
    }
}
