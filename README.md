# CRUD Mahasiswa — Microservice

Dua aplikasi **Laravel 12** menyediakan API JSON dan satu frontend **HTML + JavaScript (fetch)** mengonsumsi keduanya. Dokumentasi API dibuat otomatis menggunakan **Dedoc Scramble 0.13**. Aplikasi berjalan lokal tanpa Docker, Node.js, NPM, atau build Vite.

Tampilan mengikuti proyek `crud_mahasiswa_modular` / `crud_mahasiswa_monolith`: Bootstrap 5.3.8 lokal, navbar biru, tabel responsif, formulir tambah/edit, pagination 10 data, pesan validasi berbahasa Indonesia, dan halaman konfirmasi hapus tersendiri.

## Arsitektur

```text
                       Frontend JavaScript :8080
                         /                \
                 fetch HTTP/JSON      fetch HTTP/JSON
                       /                    \
          Laravel Service Prodi :8001    Laravel Service Mahasiswa :8002
                    |                       |
           crud_mahasiswa_prodi     crud_mahasiswa_mahasiswa
              tabel prodi              tabel mahasiswa
```

Kedua database berada pada satu server MySQL, tetapi setiap aplikasi hanya mengakses database miliknya saat runtime. Setiap layanan memiliki `composer.json`, `composer.lock`, `.env`, migration, dan pengujian sendiri; masing-masing bisa dijalankan atau di-deploy terpisah. Satu repositori dipakai untuk memudahkan pembelajaran.

- Mahasiswa menyimpan `id_prodi` sebagai referensi, tanpa foreign key lintas database atau relasi Eloquent ke Prodi.
- Saat mahasiswa ditambah/diedit, Service Mahasiswa memanggil `GET /api/prodi/{id}` pada Service Prodi.
- Saat prodi dihapus, Service Prodi memanggil `GET /api/prodi/{id}/usage` pada Service Mahasiswa.
- Frontend menggabungkan data mahasiswa dan nama prodi dari kedua API. Service Mahasiswa tidak mengakses tabel Prodi.
- Prodi dapat ditambah, dibaca, dan diedit saat Service Mahasiswa mati. Daftar/detail/penghapusan mahasiswa tetap berfungsi saat Service Prodi mati.
- Kegagalan layanan pemeriksa menolak operasi terkait dengan status `503`; prodi yang masih digunakan ditolak dengan `409`.
- Timeout antar layanan: connect 2 detik, total 4 detik. Frontend membatasi permintaan 10 detik dan menyediakan pesan kegagalan serta tombol coba lagi.

## Persyaratan

- PHP 8.2+ yang sesuai dengan dependensi terkunci, Composer 2, dan MySQL/MariaDB melalui Laragon.
- Ekstensi PHP: Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, XML, Zip, dan `pdo_mysql`.
- Konfigurasi lokal sudah diuji dengan PHP 8.2.35 dan MySQL 5.7.33.
- Browser modern dengan JavaScript aktif.

Laravel 12 dipilih agar kompatibel dengan PHP 8.2 yang tersedia. Kedua layanan tidak memerlukan frontend Laravel/Blade.

## Instalasi

Jalankan dari direktori utama proyek:

```powershell
composer install --working-dir=services/prodi
composer install --working-dir=services/mahasiswa
```

Composer memakai `composer.lock` masing-masing layanan. Optimasi classmap dinonaktifkan untuk menghindari pemindaian lambat pada lingkungan Windows ini.

Salin konfigurasi jika belum ada:

```powershell
Copy-Item services/prodi/.env.example services/prodi/.env
Copy-Item services/mahasiswa/.env.example services/mahasiswa/.env
```

Setiap `.env` sudah memiliki nama database berbeda, URL layanan pasangan, dan origin frontend. Sesuaikan `DB_HOST`, `DB_PORT`, `DB_USERNAME`, dan `DB_PASSWORD` dengan MySQL lokal. Jangan menimpa `.env` yang sudah disesuaikan.

Aktifkan MySQL di Laragon, lalu jalankan:

```powershell
php scripts/setup.php --testing
```

Setup membuat database layanan jika belum ada, membuat application key jika belum ada, menjalankan migration biasa, dan membuat database pengujian terpisah. Setup bisa diulang tanpa mereset data. Akun setup membutuhkan hak membuat database. Jika akun tidak memiliki hak itu, buat database melalui administrator lalu jalankan migration tiap layanan secara manual.

### Memakai data dari database crud_mahasiswa

Untuk instalasi pertama dengan kedua tabel tujuan masih kosong:

```powershell
php scripts/setup.php --testing --import-existing
```

Perintah tersebut membaca `crud_mahasiswa.prodi` dan `crud_mahasiswa.mahasiswa`, lalu menyalinnya dengan ID dan NIM asli ke database layanan. Database sumber tidak diubah. Impor menolak bila salah satu tabel tujuan sudah berisi data atau sumber memiliki mahasiswa tanpa prodi; tidak ada penimpaan otomatis. Penyalinan kedua tabel dilakukan dalam satu transaksi MySQL. Kedua layanan harus memakai host/kredensial yang sama saat impor, dan akun tersebut harus dapat membaca sumber serta menulis kedua tujuan. Hentikan aktivitas tulis selama impor. Sesudah impor, perubahan pada proyek monolith/modular tidak otomatis disinkronkan.

Di komputer pengembangan ini, data awal yang disalin berjumlah **2 prodi dan 2 mahasiswa**.

Jika ingin mulai dari data baru, cukup jalankan setup tanpa opsi impor, lalu tambah prodi dari frontend. Alternatif seeder prodi:

```powershell
php services/prodi/artisan db:seed
```

Seeder dapat diulang. Mahasiswa ditambahkan melalui API/frontend agar prodinya diperiksa oleh pemilik data.

## Menjalankan aplikasi

Cara singkat pada Windows PowerShell, dari direktori utama:

```powershell
.\scripts\start.ps1
```

Script menjalankan tiga proses PHP tersembunyi, memeriksa kesiapan server, dan menampilkan alamat berikut:

| Aplikasi | Alamat |
| --- | --- |
| Frontend | [http://127.0.0.1:8080](http://127.0.0.1:8080) |
| Dokumentasi Scramble Prodi | [http://127.0.0.1:8001/docs/api](http://127.0.0.1:8001/docs/api) |
| Dokumentasi Scramble Mahasiswa | [http://127.0.0.1:8002/docs/api](http://127.0.0.1:8002/docs/api) |
| OpenAPI JSON Prodi | [http://127.0.0.1:8001/docs/api.json](http://127.0.0.1:8001/docs/api.json) |
| OpenAPI JSON Mahasiswa | [http://127.0.0.1:8002/docs/api.json](http://127.0.0.1:8002/docs/api.json) |

Hentikan server yang dijalankan script:

```powershell
.\scripts\stop.ps1
```

Log dan catatan PID berada di `.runtime/`. Script stop memeriksa identitas serta waktu mulai proses agar PID yang telah digunakan ulang tidak menghentikan aplikasi lain.

Jika kebijakan PowerShell tidak mengizinkan script, gunakan tiga terminal secara manual:

```powershell
# Terminal 1, dari direktori utama
php services/prodi/artisan serve --host=127.0.0.1 --port=8001

# Terminal 2
php services/mahasiswa/artisan serve --host=127.0.0.1 --port=8002

# Terminal 3
php -S 127.0.0.1:8080 -t frontend
```

Tutup masing-masing dengan Ctrl+C. Frontend memakai hash route seperti `/#/mahasiswa`, sehingga tidak membutuhkan rewrite URL pada server statis. Jangan membuka `index.html` menggunakan `file://`.

Jika port berubah, sesuaikan `APP_URL`, `PRODI_SERVICE_URL` / `MAHASISWA_SERVICE_URL` di `.env`, `FRONTEND_ORIGINS` di kedua layanan, serta URL di `frontend/config.js`. Jalankan `php artisan config:clear` dari layanan yang konfigurasinya berubah. Script start dan smoke memakai port default; gunakan cara manual untuk port lain.

## API dan Scramble

Scramble membaca route, Form Request, API Resource, PHPDoc, dan atribut respons untuk menghasilkan **OpenAPI 3.1**. Tidak ada spesifikasi Swagger yang ditulis manual. Dokumentasi UI juga menyediakan fitur mencoba endpoint dan mengunduh spesifikasi.

Dokumentasi tersedia pada environment `local`; jangan membuka aksesnya di production tanpa mengatur otorisasi. UI Scramble memakai aset CDN bawaan paket dan membutuhkan internet saat memuat aset tersebut. API, JSON OpenAPI, dan CSS Bootstrap frontend tersedia lokal.

Pola CRUD pada Service Prodi dan Mahasiswa:

| Method | Path | Fungsi |
| --- | --- | --- |
| GET | `/api/prodi` atau `/api/mahasiswa` | Daftar dengan `?page=2`, 10 data/halaman |
| POST | `/api/prodi` atau `/api/mahasiswa` | Tambah data |
| GET | `/api/prodi/{id}` atau `/api/mahasiswa/{id}` | Detail |
| PUT/PATCH | `/api/prodi/{id}` atau `/api/mahasiswa/{id}` | Perbarui data; semua isian wajib |
| DELETE | `/api/prodi/{id}` atau `/api/mahasiswa/{id}` | Hapus data |

Endpoint tambahan:

| Layanan | Method dan path | Fungsi |
| --- | --- | --- |
| Prodi | `GET /api/prodi/options` | Semua prodi, urut nama, untuk dropdown dan pemetaan nama |
| Mahasiswa | `GET /api/prodi/{id}/usage` | Memeriksa apakah prodi memiliki mahasiswa |
| Masing-masing | `GET /up` | Health check proses Laravel; bukan pemeriksaan koneksi database |

Body tambah/edit Prodi:

```json
{"nama_prodi": "Sistem Informasi"}
```

Body tambah/edit Mahasiswa, gunakan ID prodi yang tersedia:

```json
{"id_prodi": 1, "nama": "Andi Saputra", "nim": "0012026001"}
```

Nama prodi/nama mahasiswa wajib berupa teks maksimal 100 karakter. NIM wajib berupa teks maksimal 30 karakter dan unik; angka nol di depan dipertahankan. `id_prodi` wajib bilangan bulat positif dan tersedia pada API Prodi.

Respons detail/tambah/edit dibungkus `data`. Daftar memakai `data`, `links`, dan `meta` Laravel. Respons hapus memakai `message`.

| Status | Arti |
| --- | --- |
| 200 | Baca, perbarui, atau hapus berhasil |
| 201 | Data baru dibuat |
| 404 | Data tidak ditemukan |
| 409 | Prodi masih memiliki mahasiswa |
| 422 | Validasi gagal; pesan per field dalam `errors` |
| 503 | Layanan pasangan tidak tersedia atau memberikan respons tidak valid |

Semua error pada path `/api/*` tetap JSON meskipun header Accept tidak diberikan. Frontend mengirim `Accept: application/json`.

Ekspor OpenAPI ke file untuk dibagikan atau diimpor ke Postman:

```powershell
php services/prodi/artisan scramble:export --path=docs/prodi.openapi.json
php services/mahasiswa/artisan scramble:export --path=docs/mahasiswa.openapi.json
```

Jalankan perintah dari direktori utama agar target berada di `docs/`. File ekspor adalah snapshot; jalankan ulang setelah mengubah API.

## Struktur proyek

```text
frontend/             HTML, JavaScript fetch/hash router, Bootstrap lokal
services/prodi/       Aplikasi Laravel pemilik data prodi
services/mahasiswa/   Aplikasi Laravel pemilik data mahasiswa
scripts/setup.php    Persiapan database dan opsi impor dari database lama
scripts/start.ps1    Menjalankan tiga server lokal
scripts/stop.ps1     Menghentikan server yang dicatat
scripts/smoke.php    Tes integrasi HTTP lintas layanan
docs/                Hasil ekspor OpenAPI dari Scramble
```

Folder `vendor`, file `.env`, log, dan cache tidak disertakan ke Git. Sertakan lockfile kedua layanan. Frontend menggunakan `textContent` atau escaping HTML untuk data dinamis; nama dan NIM tidak dieksekusi sebagai HTML.

## Pengujian

Database khusus tes: `crud_mahasiswa_prodi_testing` dan `crud_mahasiswa_mahasiswa_testing`. Buat keduanya dengan `php scripts/setup.php --testing`. Host/username/password mengikuti `.env` tiap layanan. `phpunit.xml` memaksa nama database tes dan TestCase menolak database lain sebelum RefreshDatabase berjalan.

```powershell
composer test --working-dir=services/prodi
composer test --working-dir=services/mahasiswa
```

Cakupan 19 tes: CRUD JSON, pagination, options, validasi field, NIM unik dan nol awal, pemeriksaan prodi melalui HTTP fake, blok hapus prodi, timeout/gangguan/respons rusak dari layanan lain, independensi operasi baca/hapus, CORS, seeder berulang, dan spesifikasi/halaman Scramble termasuk status 201 serta 503. Tes memakai MySQL karena PHP lokal tidak menyediakan driver SQLite. Tes tidak membutuhkan dua server aktif; panggilan keluar dibatasi ke fake.

Untuk menguji HTTP nyata, jalankan ketiga server, kemudian:

```powershell
php scripts/smoke.php
```

Tes HTTP membuat data sementara sendiri, memeriksa komunikasi kedua layanan dan OpenAPI, lalu menghapus data uji tersebut. Jalankan pada lingkungan pengembangan.

## Batasan contoh pembelajaran

- Tidak ada login, API gateway, message broker, container, atau orkestrator. Server pengembangan hanya bind ke `127.0.0.1`.
- Akun MySQL contoh menggunakan root lokal. Untuk deployment, buat akun berbeda dengan hak hanya pada database layanan masing-masing.
- Pemeriksaan lintas layanan berlangsung sinkron. Pemeriksaan penggunaan prodi dan penghapusannya **bukan transaksi terdistribusi**: penambahan mahasiswa dan penghapusan prodi yang benar-benar bersamaan masih memiliki celah race condition. Contoh ini ditujukan untuk demonstrasi CRUD; jaminan konsistensi konkurensi produksi memerlukan desain lanjutan seperti reservation/deletion workflow atau event dan rekonsiliasi.
- API usage hanya memeriksa referensi di database Mahasiswa, tidak membuktikan keberadaan prodi pada layanan pemilik.
- Data lama disalin sekali. Tidak ada sinkronisasi otomatis atau JOIN lintas database selama aplikasi berjalan.

## Referensi

- [Laravel 12](https://laravel.com/docs/12.x)
- [Instalasi Scramble](https://scramble.dedoc.co/installation)
- [Dokumentasi respons Scramble](https://scramble.dedoc.co/usage/response)
- [Pola Database per Service](https://microservices.io/patterns/data/database-per-service.html)
