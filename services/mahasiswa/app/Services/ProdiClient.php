<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProdiClient
{
    public function ensureExists(int $id): void
    {
        try {
            $response = Http::acceptJson()->connectTimeout(2)->timeout(4)
                ->get(rtrim(config('services.prodi.url'), '/').'/api/prodi/'.$id);
        } catch (ConnectionException $e) {
            throw new HttpException(503, 'Layanan Prodi tidak tersedia. Data mahasiswa belum disimpan.', $e);
        }

        if ($response->status() === 404) {
            throw ValidationException::withMessages(['id_prodi' => 'Prodi yang dipilih tidak ditemukan.']);
        }

        if (! $response->successful() || $response->json('data.id') !== $id) {
            abort(503, 'Layanan Prodi tidak memberikan respons yang valid. Data mahasiswa belum disimpan.');
        }
    }
}
