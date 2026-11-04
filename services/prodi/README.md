# Service Prodi

Aplikasi Laravel 12 dengan database sendiri dan API JSON. Dokumentasi otomatis menggunakan Dedoc Scramble pada `/docs/api`; OpenAPI JSON pada `/docs/api.json`.

Panduan instalasi, konfigurasi antar layanan, database, frontend, dan pengujian tersedia di [README proyek utama](../../README.md).

Jalankan dari direktori layanan:

```powershell
composer install
php artisan serve --host=127.0.0.1 --port=8001
```

Siapkan database dan `.env` mengikuti README utama sebelum menjalankan aplikasi.
