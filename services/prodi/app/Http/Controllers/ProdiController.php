<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProdiRequest;
use App\Http\Resources\ProdiResource;
use App\Models\Prodi;
use App\Services\MahasiswaClient;
use Dedoc\Scramble\Attributes\Response;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** @tags Prodi */
class ProdiController extends Controller
{
    /** Daftar prodi. Sepuluh data per halaman, diurutkan berdasarkan ID. */
    public function index(): AnonymousResourceCollection
    {
        return ProdiResource::collection(Prodi::orderBy('id')->paginate(10));
    }

    /** Pilihan prodi. Semua prodi untuk dropdown frontend, diurutkan berdasarkan nama. */
    public function options(): AnonymousResourceCollection
    {
        return ProdiResource::collection(Prodi::orderBy('nama_prodi')->get());
    }

    /**
     * Tambah prodi.
     */
    #[Response(201, 'Prodi berhasil dibuat.', type: ProdiResource::class)]
    #[IgnoreResponse(200)]
    public function store(ProdiRequest $request): ProdiResource
    {
        return new ProdiResource(Prodi::create($request->validated()));
    }

    /** Detail prodi. Digunakan Service Mahasiswa untuk memvalidasi id_prodi. */
    public function show(Prodi $prodi): ProdiResource
    {
        return new ProdiResource($prodi);
    }

    /** Perbarui prodi. */
    public function update(ProdiRequest $request, Prodi $prodi): ProdiResource
    {
        $prodi->update($request->validated());

        return new ProdiResource($prodi);
    }

    /**
     * Hapus prodi.
     *
     * Menolak dengan 409 jika prodi masih memiliki mahasiswa; 503 jika Service
     * Mahasiswa tidak tersedia. Prodi tidak dihapus pada kedua kondisi tersebut.
     */
    #[Response(503, 'Service Mahasiswa tidak tersedia atau respons tidak valid. Prodi belum dihapus.', type: 'array{message: string}')]
    public function destroy(Prodi $prodi, MahasiswaClient $mahasiswa): JsonResponse
    {
        abort_if($mahasiswa->hasMahasiswa($prodi->id), 409,
            'Prodi masih memiliki mahasiswa. Pindahkan atau hapus mahasiswa tersebut terlebih dahulu.');

        $prodi->delete();

        return response()->json(['message' => 'Prodi berhasil dihapus.']);
    }
}
