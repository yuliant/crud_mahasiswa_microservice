<?php

// Real HTTP integration test. Creates and cleans up only its own temporary records.
declare(strict_types=1);

function request(string $service, string $path, string $method = 'GET', ?array $body = null): array
{
    $port = $service === 'prodi' ? 8001 : 8002;
    $curl = curl_init('http://127.0.0.1:'.$port.$path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
    ]);
    if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
    $raw = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($raw === false) throw new RuntimeException(curl_error($curl));
    curl_close($curl);
    return [$status, json_decode($raw, true, 512, JSON_THROW_ON_ERROR)];
}

function check(bool $passed, string $message): void
{
    if (! $passed) throw new RuntimeException($message);
    echo 'PASS '.$message."\n";
}

$prodiId = null;
$mahasiswaId = null;
$failed = false;
try {
    $tag = bin2hex(random_bytes(6));
    [$status, $response] = request('prodi', '/api/prodi', 'POST', ['nama_prodi' => 'Uji HTTP '.$tag]);
    check($status === 201, 'Tambah prodi mengembalikan 201');
    $prodiId = $response['data']['id'];
    $payload = ['id_prodi' => $prodiId, 'nama' => 'Mahasiswa Uji HTTP', 'nim' => '00UJI'.$tag];
    [$status, $response] = request('mahasiswa', '/api/mahasiswa', 'POST', $payload);
    check($status === 201 && $response['data']['nim'] === $payload['nim'], 'Tambah mahasiswa melalui API Prodi dan NIM tetap teks');
    $mahasiswaId = $response['data']['id'];
    [$status] = request('prodi', '/api/prodi/'.$prodiId, 'DELETE');
    check($status === 409, 'Prodi yang digunakan tidak terhapus melalui HTTP antar layanan');
    [$status] = request('mahasiswa', '/api/mahasiswa', 'POST', $payload);
    check($status === 422, 'NIM duplikat ditolak');
    $payload['nama'] = 'Mahasiswa Uji HTTP Diperbarui';
    [$status, $response] = request('mahasiswa', '/api/mahasiswa/'.$mahasiswaId, 'PUT', $payload);
    check($status === 200 && $response['data']['nama'] === $payload['nama'], 'Edit mahasiswa dengan NIM yang sama');
    foreach (['prodi', 'mahasiswa'] as $service) {
        [$status, $spec] = request($service, '/docs/api.json');
        check($status === 200 && isset($spec['paths']['/'.$service]['post']['responses'][201]), 'Scramble '.$service.' menghasilkan OpenAPI dengan respons 201');
    }
    [$status] = request('mahasiswa', '/api/mahasiswa/'.$mahasiswaId, 'DELETE');
    check($status === 200, 'Hapus mahasiswa');
    $mahasiswaId = null;
    [$status] = request('prodi', '/api/prodi/'.$prodiId, 'DELETE');
    check($status === 200, 'Hapus prodi setelah tidak digunakan');
    $prodiId = null;
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL '.$e->getMessage()."\n");
    $failed = true;
} finally {
    try {
        if ($mahasiswaId !== null) {
            [$status] = request('mahasiswa', '/api/mahasiswa/'.$mahasiswaId, 'DELETE');
            if (! in_array($status, [200, 404], true)) throw new RuntimeException('Mahasiswa uji belum dibersihkan: '.$mahasiswaId);
        }
        if ($prodiId !== null) {
            [$status] = request('prodi', '/api/prodi/'.$prodiId, 'DELETE');
            if (! in_array($status, [200, 404], true)) throw new RuntimeException('Prodi uji belum dibersihkan: '.$prodiId);
        }
    } catch (Throwable $e) {
        fwrite(STDERR, 'Pembersihan data uji gagal: '.$e->getMessage()."\n");
        $failed = true;
    }
}
exit($failed ? 1 : 0);
