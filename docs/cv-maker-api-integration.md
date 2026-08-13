# Integrasi CV Maker (Vitae)

V-People membaca data CV melalui API internal read-only milik Vitae. API menerima hash NIK, bukan NIK mentah, dan tidak menyediakan operasi tulis atau akses file dokumen.

## Konfigurasi Vitae

```dotenv
VPEOPLE_NIK_HASH_KEY=<shared-hmac-key>
VPEOPLE_INTEGRATION_TOKEN=<shared-api-token>
```

Setelah mengaktifkan key HMAC khusus, migrasikan hash akun lama:

```bash
php artisan vitae:rehash-vpeople-niks --dry-run
php artisan vitae:rehash-vpeople-niks
php artisan config:clear
```

## Konfigurasi V-People

```dotenv
CV_MAKER_TRANSPORT=api
CV_MAKER_NIK_HASH_KEY=<shared-hmac-key>
CV_MAKER_API_BASE_URL=https://vitae.example.com
CV_MAKER_API_TOKEN=<shared-api-token>
CV_MAKER_API_TIMEOUT=15
CV_MAKER_API_CONNECT_TIMEOUT=5
```

`CV_MAKER_NIK_HASH_KEY` harus sama dengan `VPEOPLE_NIK_HASH_KEY`. Token API juga harus sama, tetapi berbeda dari `APP_KEY` kedua aplikasi.

Setelah deployment:

```bash
php artisan config:clear
php artisan route:clear
php artisan cv-maker:sync-progress --dry-run --limit=20000 --chunk=100
```

Gunakan koneksi HTTPS pada production. Jangan menaruh token atau key asli di repository dan jangan memakai akun database `root` sebagai fallback integrasi.
