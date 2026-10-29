# Uji passive liveness + GPS

Kode ini menambahkan passive liveness ke API Python yang sudah ada. Belum diunggah ke hosting. Endpoint aktif `/verify` dan referensi frontal tunggal tetap dapat dipakai selama uji bertahap.

## Batas hasil yang sudah diuji

- HRIS memisahkan mode `active` dan `passive`, mengambil tiga frame frontal berjarak selama sekitar dua detik, memeriksa kualitas/matching awal di perangkat, dan tetap memvalidasi GPS pada server.
- API memakai model anti-spoofing DeepFace/Fasnet pada selfie serta ketiga frame; setiap wajah juga dibandingkan dengan referensi akun yang sama. Referensi yang memang berupa foto tidak diperiksa sebagai bukti liveness.
- Unit test Python memakai Flask/OpenCV nyata dan engine DeepFace simulasi. Model AI, bobot model, kemampuan hosting, kecepatan inferensi, serangan foto/video, dan perangkat nyata **belum diuji**. Lulus tes kode tidak membuktikan ketahanan biometrik.
- Skor anti-spoofing adalah skor kelas model, bukan jaminan bahwa orang asli. Ambang awal 0,78 harus dievaluasi dengan sampel yang benar-benar berlabel; jangan menurunkannya hanya untuk meloloskan uji.

## 1. Tambahkan ke aplikasi Python cPanel

Gunakan virtualenv Python aplikasi API. Blueprint memerlukan Flask, Pillow, DeepFace dengan fitur `extract_faces(anti_spoofing=True)`, dan PyTorch CPU. Versi target DeepFace dicantumkan di `requirements.txt`. Instal dan uji dalam salinan aplikasi/virtualenv sebelum mengganti dependensi layanan yang sedang digunakan. Jangan menjalankan upgrade paket secara global.

`deepface[pytorch]` menambah dependensi model dan mungkin membutuhkan unduhan besar. Pastikan hosting mengizinkan paket native, PyTorch, RAM, CPU, unduhan bobot, dan durasi satu inferensi. Jika tidak diizinkan, proses AI perlu dijalankan pada layanan yang mampu menjalankan model tersebut; cron 15 menit tidak menghilangkan kebutuhan ini.

Salin `passive_verifier.py` ke folder aplikasi Flask yang sudah ada. Setelah baris `app = Flask(__name__)`, tambahkan:

```python
from passive_verifier import passive_blueprint
app.register_blueprint(passive_blueprint)
```

Jika dibuat sebagai aplikasi terpisah, gunakan `standalone_app.py` yang disertakan sebagai entry point WSGI dan `application` sebagai callable. Jangan menimpa `app.py` atau kode `/verify` lama dengan contoh lama yang ditempelkan di chat; health layanan aktif melaporkan konfigurasi yang berbeda.

Atur environment melalui pengaturan Python App cPanel. `.env.example` hanya contoh, bukan file yang otomatis dimuat oleh modul. Gunakan token yang sama dengan HRIS tanpa mencantumkannya di log. Pertahankan `FACE_MODEL` dan metric yang sesuai model referensi/kalibrasi saat ini. Health lokal layanan yang diperiksa melaporkan `SFace` / `cosine` / `opencv`.

Untuk aplikasi terpisah dengan dependensi target, `DEEPFACE_BACKEND_ENGINE=pytorch` dapat dipakai sebelum DeepFace diimpor. Jangan mengubah backend proses API lama tanpa mengujinya. Batasi ke satu proses inferensi; lock modul hanya mencegah overlap dalam satu proses. Sesuaikan batas proses WSGI juga.

Endpoint baru:

- `GET /health/passive`: status modul dan apakah runtime model pernah berhasil dimuat pada proses itu. Tidak mengunduh model atau menampilkan lokasi bobot/token.
- `POST /ready/passive`: gunakan header `X-Verify-Token`; memuat dependensi dan bobot model. Jalankan preflight ini sebelum memakai presensi. Pemuatan pertama bisa lama dan harus diselesaikan sebelum uji pengguna.
- `POST /verify-passive`: pemeriksaan pasif, menggunakan header token yang sama.

Jika dependensi/model tidak tersedia atau hasilnya tidak lengkap, API mengembalikan status perlu review dengan HTTP 503; tidak mengubah pemeriksaan kualitas gambar menjadi hasil liveness yang lolos.

### Preflight gagal dengan ModuleNotFoundError

Error ini berarti modul Python belum tersedia pada virtualenv yang menjalankan API. Baris CUDA/TensorRT tidak cukup untuk menentukan modul yang hilang; endpoint `/warmup` yang berhasil juga hanya membuktikan DeepFace dapat diimpor, bukan bahwa model anti-spoofing siap.

Versi terbaru `passive_verifier.py` menyertakan nama dependensi pada respons `/ready/passive` yang sudah diautentikasi. Unggah ulang file tersebut, restart aplikasi cPanel, lalu ulangi preflight. Contoh jika yang hilang adalah PyTorch:

```json
{"status":"pending_review","verified":false,"error_code":"missing_dependency","missing_dependency":"torch"}
```

Nama dependensi juga dicatat dalam log tanpa pesan exception, token, atau stack trace. Endpoint presensi tetap mengembalikan pesan umum. Jika respons tidak memiliki `missing_dependency`, periksa jenis error terbaru pada log; jangan menganggap semua kegagalan berasal dari PyTorch.

**Hanya jika `missing_dependency` bernilai `torch`**, aktifkan virtualenv aplikasi yang ditampilkan cPanel dan pasang paket CPU sesuai [petunjuk PyTorch](https://pytorch.org/get-started/locally/):

```sh
python -m pip install torch --index-url https://download.pytorch.org/whl/cpu
python -c "import torch; print(torch.__version__)"
```

Jika menggunakan panel cPanel tanpa Terminal, pakai **Configuration files → Run Pip Install**:

1. Unggah `requirements-passive-cpu.txt` ke folder yang sama dengan `app.py`.
2. Masukkan nama file tersebut pada **Configuration files**, klik **Add**, lalu **Save**.
3. Klik **Run Pip Install** dan pilih file CPU tersebut jika diminta. Jalankan instalasi khusus file itu; opsi index berlaku untuk seluruh pemanggilan pip, sehingga menggabungkannya dengan requirements lain dapat menyebabkan paket lain tidak ditemukan.
4. Setelah instalasi berhasil, restart aplikasi dan ulangi preflight bertoken.

Kolom **Execute python script** menerima path file Python; memasukkan `python` di sana membuat panel mencoba membuka file bernama `python`. Alur konfigurasi ini mengikuti [dokumentasi CloudLinux](https://docs.cloudlinux.com/cloudlinuxos/lve_manager/#edit-application). File requirements aplikasi yang sudah ada tetap dipertahankan.

Gunakan salinan lingkungan uji seperti petunjuk instalasi di atas. Ketersediaan wheel mengikuti versi Python dan sistem hosting. Jika instalasi ditolak, tidak ada wheel yang sesuai, atau batas RAM/disk tidak mencukupi, simpan error instalasinya untuk menentukan versi/layanan yang kompatibel. Setelah paket berhasil dipasang, restart dan jalankan preflight lagi sampai `runtime_ready: true`. Jangan melewati pemeriksaan anti-spoofing untuk mengatasi kegagalan dependensi.

### Preflight berhasil tetapi verifikasi foto gagal

`runtime_ready: true` menunjukkan impor dan pembuatan model berhasil pada proses yang menjawab. Preflight belum menjalankan deteksi wajah, inferensi anti-spoofing, atau perbandingan foto. Error `RuntimeError; missing_dependency=None` saat verifikasi belum menunjukkan bagian mana yang gagal.

Untuk membaca versi paket tanpa memuat engine AI, unggah `check_runtime.py` sejajar dengan `app.py`. Pada panel **Execute python script**, masukkan `check_runtime.py`, lalu klik **Run Script**. Script hanya menampilkan versi Python, DeepFace, PyTorch, TensorFlow, dan NumPy; paket yang tidak terpasang bernilai `null`. Cocokkan hasilnya dengan versi target di `requirements.txt` dan baris error Python sebelum mengubah dependensi. DeepFace yang belum menghasilkan field `confidence` tidak memenuhi kontrak verifier ini; jangan membuat skor pengganti untuk meloloskan hasil.

### Pembaruan terbatas untuk DeepFace 0.0.93 / TensorFlow 2.15

Pada lingkungan Python 3.10 dengan DeepFace 0.0.93, TensorFlow 2.15.1, dan NumPy 1.26.4, terdapat ketidakcocokan yang dapat direproduksi: hasil `verify()` tidak memiliki `confidence`, sehingga pemeriksaan kontrak verifier menghasilkan `RuntimeError`. DeepFace 0.0.95 menyediakan field tersebut tanpa migrasi ke keluarga dependensi baru versi 0.0.103. DeepFace 0.0.94 dihindari karena metadatanya membatasi Flask/Werkzeug ke versi lama.

Gunakan `requirements-passive-legacy-tf.txt` khusus lingkungan ini. File membatasi DeepFace ke 0.0.95, mempertahankan TensorFlow/NumPy, serta membatasi Keras/OpenCV/RetinaFace agar resolver tidak berpindah ke dependensi yang tidak cocok. PyTorch CPU yang sudah terpasang tidak diminta ulang. File `requirements.txt` tetap merupakan target untuk instalasi baru; kedua file ini adalah pilihan lingkungan yang berbeda, bukan daftar yang dipasang bersamaan.

1. Simpan lingkungan aplikasi saat ini dan uji file baru pada salinannya sebelum mengganti layanan aktif.
2. Unggah `requirements-passive-legacy-tf.txt` sejajar dengan `app.py`. Pada **Configuration files**, tambahkan file tersebut dan jalankan **Run Pip Install** khusus file itu; gunakan index PyPI yang tercantum di dalamnya. File CPU dan script `.py` tidak dipilih sebagai input instalasi ini.
3. Jalankan kembali `check_runtime.py` melalui **Execute python script → Run Script**. DeepFace harus menjadi `0.0.95`; TensorFlow/NumPy tetap `2.15.1` / `1.26.4`.
4. Restart aplikasi, jalankan preflight bertoken, lalu uji satu pengajuan presensi baru dan endpoint lama jika masih dipakai. Lulus resolusi dependensi/preflight belum membuktikan hasil inferensi nyata atau kapasitas hosting.

Jika tersedia Terminal pada virtualenv aplikasi, jalankan `python -m pip check` setelah instalasi. Jika resolver melaporkan konflik, simpan output tersebut untuk diperiksa; jangan melewati pemeriksaan dependensi dengan `--no-deps`. Pengajuan lama yang sudah masuk review tidak otomatis diproses ulang: job existing membersihkan bukti frame setelah pemrosesan, sehingga tetap perlu ditangani melalui alur HR.

Rujukan: [keluaran DeepFace 0.0.93](https://raw.githubusercontent.com/serengil/deepface/v0.0.93/deepface/modules/verification.py), [keluaran DeepFace 0.0.95](https://raw.githubusercontent.com/serengil/deepface/v0.0.95/deepface/modules/verification.py), [metadata 0.0.95](https://pypi.org/pypi/deepface/0.0.95/json).

## 2. Kontrak request/response

Multipart request:

| Field | Isi |
|---|---|
| `verification_contract` | `passive-v1` |
| `liveness_mode` / `liveness_action` | `passive` |
| `presensi_challenge_id` | ID sesi yang diterbitkan server HRIS |
| `reference_image` | Foto referensi milik akun |
| `selfie_image` | Selfie kamera saat pengajuan |
| `liveness_passive_start_image` | Frame frontal awal |
| `liveness_passive_middle_image` | Frame frontal tengah |
| `liveness_passive_end_image` | Frame frontal akhir |

HRIS memvalidasi sesi sekali pakai, urutan/jarak waktu frame, bukti GPS terbaru, radius, dan akurasi **saat pengajuan**. Model API memeriksa bukti rekaman tersebut kemudian; tidak meminta karyawan melakukan liveness ulang setelah antrean berjalan. Capture dua detik adalah format pengambilan bukti untuk API ini, bukan janji durasi inferensi.

Contoh respons lengkap:

```json
{
  "verification_contract": "passive-v1",
  "liveness_mode": "passive",
  "presensi_challenge_id": "ID-SESI-YANG-SAMA",
  "status": "verified",
  "verified": true,
  "passive_liveness_passed": true,
  "face_matched": true,
  "screen_attack_detected": false,
  "confidence": 0.99,
  "liveness_score": 0.95
}
```

Boolean harus berupa JSON boolean; kedua skor harus berupa angka 0–1. `confidence` berasal dari keluaran confidence DeepFace yang dinormalisasi dari 0–100, **bukan** rumus `1 - distance/threshold` dari contoh lama. API membandingkan embedding referensi yang dihitung sekali per permintaan dengan seluruh bukti kamera. Model/metric yang berbeda tidak boleh berbagi embedding.

HRIS hanya memberi status terverifikasi jika kontrak/mode/ID sesi sesuai, seluruh hasil lengkap, anti-spoofing lolos, wajah cocok, dan skor memenuhi konfigurasi. Respons API lama yang hanya berisi `verified: true` masuk review, bukan dianggap lolos pasif. Indikasi spoof ditolak; hasil meragukan tetap memiliki jalur review HR. Kegagalan layanan mengikuti pengaturan fail-open/fail-closed existing.

## 3. Aktifkan pada HRIS setelah preflight API lolos

Mode default tetap `active`. Tambahkan environment berikut pada lingkungan uji HRIS setelah `/ready/passive` berhasil dan uji biometrik awal memenuhi syarat:

```dotenv
PRESENSI_LIVENESS_MODE=passive
PRESENSI_PASSIVE_VERIFICATION_URL=https://face-verifier.vdnisite.com/verify-passive
PRESENSI_FACE_QUEUE=face-verification
```

Pertahankan token, URL API aktif, dan pengaturan fail-closed yang sudah ada. URL pasif sengaja terpisah agar tidak mengirim format baru ke endpoint aktif tanpa perubahan API. Perbarui cache konfigurasi/view sesuai prosedur deployment dan naikkan `ASSET_VERSION` agar script baru termuat. Tidak ada migrasi atau perubahan foto referensi yang diperlukan.

Jam presensi dicatat saat server menerima pengajuan. Pesan ke pengguna menyatakan data dicatat dan menunggu verifikasi; hasil tersedia pada riwayat. API tidak menerima keputusan GPS dari browser untuk menyetujui identitas.

## 4. Worker cron 15 menit

Worker yang dijalankan cron harus memproses queue khusus `face-verification`, bukan seluruh queue PDF/import/email. Contoh bagian perintah cron Linux (ganti seluruh path sesuai hosting):

```sh
flock -n /home/USER/tmp/face-verification.lock /PATH/PHP /home/USER/hris/artisan queue:work database --queue=face-verification --stop-when-empty --max-jobs=5 --max-time=50 --sleep=1 --tries=1
```

Atur jadwal cPanel menjadi setiap 15 menit. Pastikan `flock` tersedia atau pakai lock scheduler yang setara. Jangan mengubah cron `schedule:run` global menjadi 15 menit karena tugas lain dapat terlewat.

`--max-time` diperiksa di antara job; tidak memotong job yang sedang berjalan. Timeout job existing 240 detik, sehingga angka CLI `--timeout` tidak boleh dianggap otomatis menggantikan properti job itu. Batasi timeout HTTP API sesuai durasi proses hosting, beri margin untuk satu job terakhir, dan pastikan `retry_after` lebih besar dari batas job agar tidak diproses bersamaan. Jangan menyalin batas waktu contoh sebelum mengukur satu inferensi di hosting.

Waktu tunggu bisa lebih dari 15 menit saat antrean menumpuk. Lima job per eksekusi hanya contoh batas awal, bukan kapasitas untuk 5.000 pengguna. Ukur kedatangan pengajuan, rata-rata durasi API, backlog, dan kegagalan sebelum produksi. Gunakan monitor antrean/riwayat review existing; jangan menghapus job tertunda untuk memperpendek antrean.

Contoh lima job setiap 15 menit hanya menghasilkan 20 pemeriksaan per jam. Kapasitas ini tidak memadai untuk jam masuk ribuan karyawan. Jika kuota hosting tidak dapat memenuhi laju pengajuan, pisahkan proses verifier/worker ke server yang memiliki kapasitas cukup sambil mempertahankan HRIS pada hosting saat ini.

## Batas koneksi database pada shared hosting

Error MySQL 1226 dengan `max_user_connections` bernilai 5 menunjukkan kuota koneksi bersamaan akun database telah tercapai. Kuota digunakan bersama oleh request web, worker, scheduler, dan aplikasi lain yang memakai akun yang sama. Query yang gagal membuka koneksi belum membuktikan query tersebut penyebab kehabisan slot.

HRIS melepas koneksi MySQL/MariaDB yang sedang tidak berada dalam transaksi tepat sebelum menunggu HTTP verifier. Semua lookup yang diperlukan sudah selesai pada batas ini; transaksi penyimpanan hasil membuka koneksi kembali melalui Laravel. Koneksi dalam transaksi serta SQLite in-memory tetap dipertahankan. Scheduler resign tetap berjalan setiap menit dengan mutex agar eksekusinya tidak bertumpuk; mutex kedaluwarsa setelah 30 menit jika proses berhenti tanpa pembersihan normal.

Deploy perubahan `app/Services/Presensi/ServerSideFaceVerificationService.php` dan `app/Console/Kernel.php`. Worker memakai kode baru pada proses berikutnya. Gunakan satu cron `schedule:run` setiap menit, dengan cache persisten yang dapat ditulis. Pengecekan lokal mencakup pelepasan PDO, penulisan hasil setelah reconnect, rollback transaksi aktif, dan keberlangsungan SQLite in-memory; 16 tes Laravel terkait lolos dengan 132 assertion.

Pelepasan koneksi selama menunggu AI mengurangi okupansi slot, tetapi kuota 5 masih dapat tercapai saat akses bersamaan. Minta penyedia hosting memeriksa proses pemegang koneksi serta menyesuaikan kuota berdasarkan beban nyata. Batas tersebut dikendalikan oleh server database; mengubah timeout API atau memori worker tidak menambah kuota koneksi. Pengujian MySQL produksi dan inventaris koneksi aplikasi lain belum dilakukan.

## Uji penerimaan

1. Foto frontal milik akun, GPS di dalam radius, cahaya merata: tercatat, kemudian hasil terverifikasi jika API lolos.
2. GPS di luar radius/akurasi buruk/bukti lama: ditolak sebelum menyimpan presensi atau mengantrekan API.
3. Wajah keluar frame atau kamera berhenti saat capture: bukti dibuang dan pengambilan dimulai ulang.
4. Foto cetak, foto di layar, rekaman video, wajah orang lain: tidak boleh otomatis terverifikasi. Ukur salah-terima dan salah-tolak di beberapa perangkat/pencahayaan.
5. Salah satu frame berisi orang berbeda atau terindikasi spoof: tidak lolos hanya karena selfie akhir cocok.
6. API lama, skor/flag hilang, ID sesi berbeda, token salah, model belum tersedia, API timeout: tidak menjadi terverifikasi; pengguna/HR dapat melihat status sesuai hasil/kebijakan.
7. Cron berhenti/antrean bertumpuk: jam awal tidak berubah, status menunggu tetap terlihat, dan operasi HR tidak diberi hasil sukses palsu.

Rollback: kembalikan `PRESENSI_LIVENESS_MODE=active` dan perbarui cache konfigurasi. Job pasif yang sudah tersimpan tetap membawa mode sesinya sendiri dan menggunakan URL pasif; jangan menonaktifkan endpoint/worker pasif sebelum backlog selesai atau ditangani melalui review HR.

## Hasil verifikasi kode lokal

- 95 tes Laravel / 3.948 assertion lolos dalam dua kelompok proses (33 + 62 tes).
- 9 tes JavaScript lolos, termasuk simulasi pengambilan frame frontal dan reset ketika wajah hilang.
- 15 tes Python dengan Flask/OpenCV nyata dan DeepFace simulasi lolos, termasuk reproduksi keluaran legacy tanpa `confidence`. Tidak memasang atau menjalankan model DeepFace/PyTorch pada komputer pengguna selama uji ini.
- Blade berhasil dikompilasi; tujuh script inline dari hasil render halaman dan script capture eksternal lolos pemeriksaan syntax.
- Pratinjau statis dari Blade dengan data fiktif diperiksa pada lebar 390 px tanpa overflow horizontal. Script kamera/GPS sengaja tidak dijalankan pada pratinjau; ini bukan uji perangkat nyata.
- Satu rangkaian tes gabungan mencapai batas memori CLI 128 MB saat membuat fixture Excel. Seluruh kelompok terkait lolos saat dipisah; limit produksi tidak diubah.
- Health API yang diberikan berhasil dibaca dengan HTTP 200 dan melaporkan SFace/OpenCV serta parameter gerakan kepala. Tidak mengirim foto karyawan, menjalankan presensi sebenarnya, memasang API ke hosting, menjadwalkan cron, atau mengaktifkan mode pasif pada konfigurasi aplikasi yang sedang digunakan.

Rujukan implementasi: [DeepFace extract_faces](https://github.com/serengil/deepface/blob/master/deepface/modules/detection.py), [Fasnet](https://github.com/serengil/deepface/blob/master/deepface/models/spoofing/pytorch/FasNet.py), [confidence verifikasi](https://github.com/serengil/deepface/blob/master/deepface/modules/verification.py), [worker Laravel](https://laravel.com/framework/docs/13.x/queues).
