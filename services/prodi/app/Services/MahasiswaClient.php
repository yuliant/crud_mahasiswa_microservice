<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MahasiswaClient
{
    public function hasMahasiswa(int $prodiId): bool
    {
        try {
            $response = Http::acceptJson()->connectTimeout(2)->timeout(4)
                ->get(rtrim(config('services.mahasiswa.url'), '/').'/api/prodi/'.$prodiId.'/usage');
        } catch (ConnectionException $e) {
            throw new HttpException(503, 'Layanan Mahasiswa tidak tersedia. Prodi belum dihapus.', $e);
        }

        if (! $response->successful() || ! is_bool($response->json('data.has_mahasiswa')) ||
            $response->json('data.id_prodi') !== $prodiId) {
            abort(503, 'Layanan Mahasiswa tidak memberikan respons yang valid. Prodi belum dihapus.');
        }

        return $response->json('data.has_mahasiswa');
    }
}
