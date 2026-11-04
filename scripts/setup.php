<?php

// Development setup only. Runtime services never connect to each other's databases.
declare(strict_types=1);

$root = dirname(__DIR__);
if (! is_file($root.'/services/prodi/vendor/autoload.php')) {
    fwrite(STDERR, "Jalankan composer install di services/prodi dan services/mahasiswa terlebih dahulu.\n");
    exit(1);
}
require $root.'/services/prodi/vendor/autoload.php';

function runArtisan(string $service, array $arguments): void
{
    global $root;
    $process = proc_open([PHP_BINARY, 'artisan', ...$arguments, '--no-ansi'], [STDIN, STDOUT, STDERR], $pipes, $root.'/services/'.$service);
    if (! is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Perintah artisan gagal pada Service '.$service.'.');
    }
}

function connection(array $env, ?string $database = null): PDO
{
    $dsn = 'mysql:host='.($env['DB_HOST'] ?? '127.0.0.1').';port='.($env['DB_PORT'] ?? '3306').';charset=utf8mb4';
    if ($database !== null) $dsn .= ';dbname='.$database;
    return new PDO($dsn, $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function identifier(string $name): string
{
    if (! preg_match('/^[a-zA-Z0-9_]+$/', $name)) throw new RuntimeException('Nama database hanya boleh berisi huruf, angka, dan underscore.');
    return '`'.$name.'`';
}

try {
    $unknown = array_filter(array_slice($argv, 1), fn ($arg) => ! in_array($arg, ['--testing', '--import-existing'], true));
    if ($unknown) throw new RuntimeException('Opsi: --testing (buat DB tes), --import-existing (salin crud_mahasiswa ke DB kosong).');
    $configs = [];
    foreach (['prodi', 'mahasiswa'] as $service) {
        $directory = $root.'/services/'.$service;
        if (! is_file($directory.'/vendor/autoload.php')) throw new RuntimeException('Dependensi Service '.$service.' belum dipasang.');
        if (! is_file($directory.'/.env')) copy($directory.'/.env.example', $directory.'/.env');
        $configs[$service] = Dotenv\Dotenv::parse(file_get_contents($directory.'/.env'));
        if (empty($configs[$service]['APP_KEY'])) runArtisan($service, ['key:generate']);
        $name = $configs[$service]['DB_DATABASE'] ?? '';
        if ($name === '' || $name === 'crud_mahasiswa') throw new RuntimeException('Gunakan database layanan terpisah; jangan gunakan crud_mahasiswa sebagai target.');
        identifier($name);
    }
    if ($configs['prodi']['DB_DATABASE'] === $configs['mahasiswa']['DB_DATABASE']) {
        throw new RuntimeException('Service Prodi dan Mahasiswa harus memakai database yang berbeda.');
    }
    foreach ($configs as $service => $env) {
        $server = connection($env);
        $name = identifier($env['DB_DATABASE']);
        $server->exec('CREATE DATABASE IF NOT EXISTS '.$name.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        echo 'Database '.$env['DB_DATABASE']." siap.\n";
        runArtisan($service, ['config:clear']);
        runArtisan($service, ['migrate', '--force']);
        if (in_array('--testing', $argv, true)) {
            $testName = 'crud_mahasiswa_'.$service.'_testing';
            $server->exec('CREATE DATABASE IF NOT EXISTS '.identifier($testName).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            echo 'Database tes '.$testName." siap.\n";
        }
    }
    if (in_array('--import-existing', $argv, true)) {
        // One MySQL transaction across the two new databases on the same server.
        foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
            if (($configs['prodi'][$key] ?? '') !== ($configs['mahasiswa'][$key] ?? '')) {
                throw new RuntimeException('Impor membutuhkan host dan kredensial kedua layanan yang sama, dengan akses baca database sumber.');
            }
        }
        $pdo = connection($configs['prodi'], 'crud_mahasiswa');
        $prodi = identifier($configs['prodi']['DB_DATABASE']).'.prodi';
        $mahasiswa = identifier($configs['mahasiswa']['DB_DATABASE']).'.mahasiswa';
        $pdo->beginTransaction();
        try {
            if ((int) $pdo->query('SELECT COUNT(*) FROM '.$prodi)->fetchColumn() ||
                (int) $pdo->query('SELECT COUNT(*) FROM '.$mahasiswa)->fetchColumn()) {
                throw new RuntimeException('Impor dibatalkan: kedua tabel tujuan harus kosong. Data yang ada tidak ditimpa.');
            }
            $orphans = (int) $pdo->query('SELECT COUNT(*) FROM mahasiswa m LEFT JOIN prodi p ON p.id=m.id_prodi WHERE p.id IS NULL')->fetchColumn();
            if ($orphans) throw new RuntimeException('Impor dibatalkan: terdapat mahasiswa tanpa prodi pada database sumber.');
            $countProdi = $pdo->exec('INSERT INTO '.$prodi.' (id,nama_prodi) SELECT id,nama_prodi FROM crud_mahasiswa.prodi');
            $countMahasiswa = $pdo->exec('INSERT INTO '.$mahasiswa.' (id,id_prodi,nama,nim) SELECT id,id_prodi,nama,nim FROM crud_mahasiswa.mahasiswa');
            $pdo->commit();
            echo "Tersalin: $countProdi prodi, $countMahasiswa mahasiswa. Database sumber tidak diubah.\n";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
    echo "Setup selesai. Jalankan .\\scripts\\start.ps1 atau tiga server secara manual (lihat README).\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Setup gagal: '.$e->getMessage()."\n");
    exit(1);
}
