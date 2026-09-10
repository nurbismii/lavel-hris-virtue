# Terjemahan antarmuka Mandarin

Antarmuka memakai locale `zh_CN` (Mandarin sederhana). Label dan petunjuk baru
memakai helper Laravel `__('Teks Indonesia')` dengan terjemahan di
`resources/lang/zh_CN.json`. Kamus terstruktur yang sudah ada di `id` dan `zh_CN`
tetap digunakan untuk navigasi, validasi, dan komponen bersama.

## Menambah teks

- Bungkus teks tampilan dengan `{{ __('Teks Indonesia') }}` dan tambahkan pasangan
  Mandarin ke JSON. Bahasa Indonesia menggunakan teks asal sebagai fallback.
- Untuk literal JavaScript gunakan `@js(__('Teks Indonesia'))`, terutama jika
  teks mengandung koma. Proyek saat ini menggunakan Laravel 13; directive `@json`
  memisahkan argumen berdasarkan koma sehingga tidak cocok untuk semua literal.
- Pertahankan placeholder seperti `:name`, `:count`, `_START_`, dan `_TOTAL_`.
- Terjemahkan label opsi, bukan `value`, nama input, kode status, atau data karyawan.
- Teks kontrak, isi dokumen, dan data dari sistem eksternal memerlukan penanganan
  tersendiri; kamus antarmuka tidak menerjemahkan isi data tersebut otomatis.

## Validasi

```bash
php artisan test --filter=MandarinLocalizationTest
php artisan view:cache
```

Pemeriksaan manual:

1. Pilih Mandarin melalui pemilih bahasa. Periksa login/pemulihan password,
   dashboard, data karyawan, roster, approval, kontrak, dan pengaturan master.
2. Periksa judul, tombol, petunjuk, placeholder, konfirmasi, dan pesan loading.
3. Pada formulir roster, label menjadi Mandarin tetapi nilai pilihan tetap
   `OFF` dan `BEKERJA`. Pastikan pemilihan ulang bahasa Indonesia tetap benar.
4. Periksa console browser saat membuka dashboard CV dan memuat tabel.

Deploy file view dan kamus secara bersamaan, lalu jalankan `php artisan view:clear`
atau bangun ulang cache view. Tidak memerlukan migration atau perubahan data.
Jika perlu rollback, pulihkan versi file view dan kamus sebelum perubahan ini,
lalu bersihkan cache view.
