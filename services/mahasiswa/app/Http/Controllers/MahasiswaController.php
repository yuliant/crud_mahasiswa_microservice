<?php

namespace App\Http\Controllers;

use App\Http\Requests\MahasiswaRequest;
use App\Http\Resources\MahasiswaResource;
use App\Models\Mahasiswa;
use App\Services\ProdiClient;
use Dedoc\Scramble\Attributes\Response;
use Dedoc\Scramble\Attributes\IgnoreResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** @tags Mahasiswa */
class MahasiswaController extends Controller
{
    /** Daftar mahasiswa. Sepuluh data per halaman. Nama prodi diambil frontend dari API Prodi. */
    public function index(): AnonymousResourceCollection
    {
        return MahasiswaResource::collection(Mahasiswa::orderBy('id')->paginate(10));
    }

    /**
     * Tambah mahasiswa.
     *
     * id_prodi diperiksa melalui HTTP ke Service Prodi sebelum disimpan.
     */
    #[Response(201, 'Mahasiswa berhasil dibuat.', type: MahasiswaResource::class)]
    #[IgnoreResponse(200)]
    #[Response(503, 'Service Prodi tidak tersedia atau respons tidak valid. Mahasiswa belum disimpan.', type: 'array{message: string}')]
    public function store(MahasiswaRequest $request, ProdiClient $prodi): MahasiswaResource
    {
        $data = $request->validated();
        $prodi->ensureExists((int) $data['id_prodi']);

        return new MahasiswaResource(Mahasiswa::create($data));
    }

    /** Detail mahasiswa. */
    public function show(Mahasiswa $mahasiswa): MahasiswaResource
    {
        return new MahasiswaResource($mahasiswa);
    }

    /** Perbarui mahasiswa. NIM tetap unik; prodi diperiksa ulang melalui API. */
    #[Response(503, 'Service Prodi tidak tersedia atau respons tidak valid. Mahasiswa belum diperbarui.', type: 'array{message: string}')]
    public function update(MahasiswaRequest $request, Mahasiswa $mahasiswa, ProdiClient $prodi): MahasiswaResource
    {
        $data = $request->validated();
        $prodi->ensureExists((int) $data['id_prodi']);
        $mahasiswa->update($data);

        return new MahasiswaResource($mahasiswa);
    }

    /** Hapus mahasiswa. Tetap dapat digunakan saat Service Prodi tidak tersedia. */
    public function destroy(Mahasiswa $mahasiswa): JsonResponse
    {
        $mahasiswa->delete();

        return response()->json(['message' => 'Mahasiswa berhasil dihapus.']);
    }

    /** Periksa penggunaan prodi. Dipanggil Service Prodi sebelum penghapusan dan oleh frontend untuk konfirmasi. */
    public function usage(int $id): JsonResponse
    {
        return response()->json([
            'data' => [
                'id_prodi' => $id,
                'has_mahasiswa' => Mahasiswa::where('id_prodi', $id)->exists(),
            ],
        ]);
    }
}
