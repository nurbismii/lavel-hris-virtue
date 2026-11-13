# Model deteksi cadangan presensi

Model tambahan berasal dari [face-api.js commit a86f011d72124e5fb93e59d5c4ab98f699dd5c9c](https://github.com/justadudewhohacks/face-api.js/tree/a86f011d72124e5fb93e59d5c4ab98f699dd5c9c/weights). Lisensi upstream disertakan pada `LICENSE`.

Landmark penuh `face_landmark_68_model` dipakai untuk seluruh pengukuran wajah. `ssd_mobilenetv1_model` menjadi cadangan bila Tiny pada input 320 dan 512 tidak menemukan tepat satu wajah dengan skor yang cukup. SSD dimuat sekali saat diperlukan, lalu dipakai selama halaman masih terbuka. Manifest dan seluruh shard harus ditempatkan pada direktori `weights/` yang sama. Berkas Tiny lama tetap tersedia untuk kompatibilitas.

| Berkas | SHA-256 |
| --- | --- |
| `ssd_mobilenetv1_model-shard1` | `6ff16f095b0371f24accb48aab82e595697baa821cf851167cbf9203d1d7ec9a` |
| `ssd_mobilenetv1_model-shard2` | `2539847e627c8c4a561e999170735e6c527deecc6fdba2959fc5312fb1ea1de3` |
| `face_landmark_68_model-shard1` | `4611ef65c87d836d03d684b30eec4d195d8b219fa1dd58fc58945831c6b9299b` |

Model berhasil didekode/dimuat dengan `face-api.min.js` yang sudah ada; inferensi SSD menolak tensor gambar kosong. Tes ini memeriksa integrasi runtime dan berkas, bukan akurasi deteksi pada pengguna berkerudung atau kecepatan HP.
