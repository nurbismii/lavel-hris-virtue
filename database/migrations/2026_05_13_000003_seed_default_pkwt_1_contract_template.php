<?php

use App\Models\ContractTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contract_templates')) {
            return;
        }

        $exists = DB::table('contract_templates')
            ->where('contract_type', ContractTemplate::TYPE_PKWT_1)
            ->where('name', 'PKWT 1 - VDNI Draft dari Contoh')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('contract_templates')->insert([
            'contract_type' => ContractTemplate::TYPE_PKWT_1,
            'name' => 'PKWT 1 - VDNI Draft dari Contoh',
            'letterhead_html' => $this->letterheadHtml(),
            'body_html' => $this->bodyHtml(),
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve templates because HR may have edited the seeded content.
    }

    private function letterheadHtml(): string
    {
        return <<<'HTML'
<table style="width: 100%; border-collapse: collapse; border: 0;">
    <tr>
        <td style="border: 0; width: 24%; vertical-align: top;">
            <img src="/assets/img/logo-company.png" alt="PT VDNI" style="width: 120px; height: auto;">
        </td>
        <td style="border: 0; text-align: right; vertical-align: top;">
            <strong>PT VIRTUE DRAGON NICKEL INDUSTRY</strong><br>
            <span style="font-size: 10px;">www.vdni.co.id | PH (+6221) 5154408 | FX (+6221) 5153595</span>
        </td>
    </tr>
</table>
<hr style="border: 0; border-top: 1px solid #111; margin: 8px 0 18px;">
HTML;
    }

    private function bodyHtml(): string
    {
        return <<<'HTML'
<h2 style="text-align: center; margin-bottom: 4px;">PERJANJIAN KERJA WAKTU TERTENTU</h2>
<p style="text-align: center; margin-top: 0;"><strong>{{no_pkwt}}</strong></p>

<p>Perjanjian Kerja Waktu Tertentu ini (untuk selanjutnya disebut "Perjanjian Kerja"), dibuat dan ditandatangani oleh dan antara:</p>

<p><strong>PT VIRTUE DRAGON NICKEL INDUSTRY</strong>, yang berkedudukan di Jakarta (untuk selanjutnya disebut sebagai "PERUSAHAAN"); dan</p>

<p><strong>{{nama_karyawan}}</strong>, {{status_pernikahan}}, dalam hal ini mewakili dirinya sendiri, beralamat di {{alamat}}, No. KTP: {{no_ktp}}, masa berlaku seumur hidup (untuk selanjutnya disebut sebagai "PEKERJA").</p>

<p>PERUSAHAAN dan PEKERJA masing-masing disebut sebagai "Pihak" dan bersama-sama disebut sebagai "Para Pihak".</p>

<p>Terlebih dahulu Para Pihak menerangkan sebagai berikut:</p>
<ol>
    <li>Bahwa PERUSAHAAN adalah sebuah badan hukum yang didirikan berdasarkan hukum Republik Indonesia.</li>
    <li>Bahwa PEKERJA melamar di PERUSAHAAN dan bersedia bekerja dengan baik, penuh tanggung jawab serta mematuhi segala kondisi kerja dan peraturan-peraturan serta instruksi dari PERUSAHAAN.</li>
    <li>Bahwa PERUSAHAAN menerima lamaran kerja yang diajukan oleh PEKERJA untuk dipekerjakan di lokasi proyek PERUSAHAAN atau tempat lain sesuai kebutuhan PERUSAHAAN.</li>
    <li>Bahwa Para Pihak telah sepakat dan karenanya mengikatkan diri pada Perjanjian Kerja ini sesuai dengan syarat dan ketentuan-ketentuan sebagai berikut:</li>
</ol>

<h3 style="text-align: center;">PASAL 1<br>RUANG LINGKUP</h3>
<p>Perjanjian Kerja ini melingkupi segala sesuatu yang berkaitan dengan pekerjaan yang diberikan oleh PERUSAHAAN kepada PEKERJA.</p>

<h3 style="text-align: center;">PASAL 2<br>JABATAN DAN LOKASI KERJA</h3>
<p>PERUSAHAAN mempekerjakan PEKERJA sebagai <strong>{{jabatan}}</strong> dengan syarat dasar bahwa PEKERJA bersedia untuk:</p>
<ol>
    <li>Mematuhi segala instruksi baik lisan maupun tertulis yang diberikan oleh PERUSAHAAN.</li>
    <li>Dimutasi/dirotasi/ditempatkan ke lokasi kerja yang lain sesuai dengan kebutuhan PERUSAHAAN.</li>
</ol>
<p>Desa Morosi, Kabupaten Konawe, Sulawesi Tenggara ditetapkan sebagai lokasi kerja PEKERJA. Jika dibutuhkan oleh PERUSAHAAN, PEKERJA dengan ini setuju untuk ditempatkan/ditugaskan/dimutasi ke lokasi kerja lain dan/atau di departemen/divisi lain sebagaimana ditentukan oleh PERUSAHAAN.</p>

<h3 style="text-align: center;">PASAL 3<br>JANGKA WAKTU</h3>
<p>Perjanjian Kerja ini berlaku untuk jangka waktu selama <strong>{{durasi_kontrak}}</strong>, terhitung sejak tanggal <strong>{{tanggal_mulai_kontrak}}</strong> dan berakhir secara hukum pada tanggal <strong>{{tanggal_berakhir_kontrak}}</strong> ("Jangka Waktu Kerja").</p>
<p>Dalam hal Jangka Waktu Kerja ingin diperpanjang, maka PERUSAHAAN akan memberitahukan kepada PEKERJA sekurang-kurangnya 7 (tujuh) hari sebelum Perjanjian Kerja ini berakhir.</p>
<p>Jangka waktu Perjanjian Kerja ini dapat berakhir sebelum berakhirnya masa Perjanjian bila:</p>
<ol>
    <li>Atas kehendak PEKERJA dengan memberitahukan secara tertulis kepada PERUSAHAAN minimal 30 (tiga puluh) hari sebelum tanggal rencana berakhirnya Perjanjian Kerja ini.</li>
    <li>Atas kehendak PERUSAHAAN kepada PEKERJA dalam hal PEKERJA melakukan pelanggaran dalam Pasal 9 dalam Perjanjian Kerja ini dengan tunduk pada prosedur pemutusan hubungan kerja sesuai dengan peraturan perundang-undangan.</li>
</ol>

<h3 style="text-align: center;">PASAL 4<br>WAKTU DAN HARI KERJA</h3>
<ol>
    <li>PEKERJA diwajibkan mematuhi Waktu Kerja yang telah ditentukan oleh PERUSAHAAN.</li>
    <li>Untuk posisi pekerjaan yang memberlakukan hari dan jam kerja khusus akan ditentukan sesuai dengan ketentuan perundangan yang berlaku.</li>
    <li>PERUSAHAAN berhak untuk mengubah hari dan jam kerja berdasarkan sifat pekerjaan, kebutuhan dan kondisi Perusahaan, dengan tetap mengacu pada ketentuan perundang-undangan yang berlaku.</li>
</ol>

<h3 style="text-align: center;">PASAL 5<br>STATUS PEKERJA DAN UPAH</h3>
<p>PEKERJA dengan ini menyatakan kesanggupan dan kemampuannya untuk melakukan kewajiban-kewajiban yang diberikan oleh PERUSAHAAN kepadanya dan oleh karenanya PERUSAHAAN akan memberikan upah secara bulanan kepada PEKERJA.</p>
<p>Upah Pekerja adalah sebesar <strong>{{gaji}}</strong>, terdiri dari komponen tetap dan komponen tidak tetap ("Upah"):</p>
<p><strong>Komponen tetap:</strong></p>
<table style="width: 100%; border-collapse: collapse;">
    <tr><td>Upah Pokok</td><td style="width: 5%; text-align: center;">:</td><td>{{gaji}}</td><td>,- / Bulan</td></tr>
    <tr><td>Tunjangan Jabatan</td><td style="text-align: center;">:</td><td>-</td><td>,- / Bulan</td></tr>
</table>
<p><strong>Komponen tidak tetap:</strong></p>
<table style="width: 100%; border-collapse: collapse;">
    <tr><td>Tunjangan Uang Makan</td><td style="width: 5%; text-align: center;">:</td><td>{{uang_makan}}</td><td>,- / Hari Kerja</td></tr>
    <tr><td>Hour Machine (HM)</td><td style="text-align: center;">:</td><td>-</td><td>,- / Jam</td></tr>
    <tr><td>Tunjangan Lembur</td><td style="text-align: center;">:</td><td>-</td><td>,- / Jam</td></tr>
</table>

<h3 style="text-align: center;">PASAL 6<br>KETENTUAN PEKERJAAN</h3>
<ol>
    <li>Apabila PEKERJA melakukan pelanggaran terhadap peraturan Perjanjian Kerja ini dan/atau ketentuan-ketentuan lainnya, maka PERUSAHAAN berhak untuk mengambil/memutuskan langkah-langkah tegas berupa teguran lisan, pemberian Surat Peringatan (SP) sampai Pemutusan Hubungan Kerja ("PHK").</li>
    <li>PEKERJA menyetujui untuk meningkatkan disiplin kerja. Apabila PEKERJA tidak masuk kerja tanpa keterangan, izin di luar ketentuan, sakit tanpa bukti tertulis yang sah, atau meninggalkan pekerjaan tanpa izin atasan dan HRD, maka Upah tidak dibayarkan sesuai perhitungan yang berlaku.</li>
    <li>PEKERJA menyetujui bahwa jika dipandang perlu, PERUSAHAAN dapat menempatkan PEKERJA pada tugas atau jenis pekerjaan lain yang sesuai dengan kemampuannya, tanpa adanya perubahan gaji, kecuali jika PEKERJA ditugaskan melakukan pekerjaan dengan klasifikasi yang lebih tinggi.</li>
    <li>Bagi PEKERJA yang berhak atas tunjangan lembur, maka untuk kelebihan jam kerja akan diperhitungkan sebagai kerja lembur.</li>
    <li>PERUSAHAAN akan mengikutsertakan PEKERJA dalam program BPJS Ketenagakerjaan dan BPJS Kesehatan sesuai ketentuan peraturan perundang-undangan yang berlaku.</li>
    <li>Segala jenis pajak penghasilan yang timbul dari pembayaran Upah, THR dan kompensasi lainnya oleh PERUSAHAAN menjadi tanggungan PERUSAHAAN.</li>
    <li>Upah akan dibayar setiap bulan dengan cara transfer bank.</li>
</ol>

<h3 style="text-align: center;">PASAL 7<br>TUNJANGAN HARI RAYA</h3>
<p>Tunjangan Hari Raya ("THR") dibayarkan oleh PERUSAHAAN kepada PEKERJA sebanyak 1 (satu) kali dalam satu tahun sesuai ketentuan peraturan perundang-undangan yang berlaku.</p>
<p>THR dibayarkan oleh PERUSAHAAN kepada PEKERJA dalam kurun waktu selambat-lambatnya 7 (tujuh) hari sebelum Hari Raya.</p>

<h3 style="text-align: center;">PASAL 8<br>JAMINAN ATAS DATA PEKERJA DAN INFORMASI</h3>
<ol>
    <li>PEKERJA dengan ini menjamin bahwa segala informasi dan data, termasuk tetapi tidak terbatas pada ijazah, identitas pribadi dan lain-lain yang telah disampaikan kepada PERUSAHAAN adalah benar.</li>
    <li>Segala perubahan yang terjadi pada PEKERJA, seperti identitas, alamat, nomor telepon, status dan sebagainya wajib diberitahukan kepada PERUSAHAAN dalam waktu 7 (tujuh) hari setelah adanya perubahan tersebut.</li>
</ol>

<h3 style="text-align: center;">PASAL 9<br>TATA TERTIB</h3>
<ol>
    <li>PEKERJA wajib menyelesaikan tugas dengan baik dan penuh tanggung jawab.</li>
    <li>PEKERJA terikat serta tetap tunduk kepada semua ketentuan dan tata tertib dalam PERUSAHAAN serta ketentuan lainnya yang ditetapkan Pimpinan Perusahaan.</li>
    <li>PEKERJA dilarang keras melakukan pelanggaran tata tertib, termasuk penipuan, pencurian, penggelapan, pemberian keterangan palsu, penyalahgunaan narkotika, perbuatan asusila, perjudian, kekerasan, pengancaman, perusakan barang milik perusahaan, pembocoran rahasia perusahaan, dan perbuatan lain yang diancam pidana.</li>
    <li>PEKERJA wajib menjaga dan menggunakan peralatan serta perlengkapan kerja secara bertanggung jawab.</li>
    <li>PEKERJA dilarang mempengaruhi warga atau rekan kerja yang dapat menimbulkan kekhawatiran atau kecemasan bagi PERUSAHAAN.</li>
    <li>Apabila PEKERJA mengundurkan diri atau terjadi PHK, PEKERJA wajib mengembalikan seluruh peralatan dan/atau fasilitas milik PERUSAHAAN.</li>
    <li>Apabila dalam 5 (lima) hari kerja secara terus-menerus PEKERJA tidak masuk kerja tanpa keterangan tertulis dan bukti sah, maka tindakan tersebut dapat dianggap sebagai pernyataan pengunduran diri sepihak.</li>
</ol>

<h3 style="text-align: center;">PASAL 10<br>SANKSI</h3>
<p>PERUSAHAAN berhak memberikan sanksi berupa teguran, peringatan, skorsing, ataupun PHK sesuai intensitas dan bobot pelanggaran yang dilakukan oleh PEKERJA.</p>
<p>Apabila PEKERJA dengan sengaja atau lalai menjalankan tugas dan tanggung jawabnya sehingga menyebabkan kerugian PERUSAHAAN, maka PEKERJA wajib mengganti rugi sesuai kebijakan PERUSAHAAN.</p>

<h3 style="text-align: center;">PASAL 11<br>BERAKHIRNYA HUBUNGAN PEKERJAAN</h3>
<p>Tanpa mengurangi ketentuan lain dalam Perjanjian Kerja ini, Perjanjian Kerja ini akan berakhir apabila PEKERJA meninggal dunia, berakhirnya Jangka Waktu Kerja, adanya putusan pidana atau putusan lembaga penyelesaian perselisihan hubungan industrial yang telah berkekuatan hukum tetap, atau adanya keadaan tertentu yang diatur dalam Perjanjian Kerja ini atau Peraturan Perusahaan.</p>
<p>Dengan alasan mendesak, PERUSAHAAN dapat mengakhiri hubungan kerja dengan PEKERJA secara seketika tanpa ganti rugi apapun apabila PEKERJA melakukan pelanggaran tata tertib dalam Pasal 9 ayat 3 Perjanjian ini.</p>
<p>Apabila salah satu Pihak mengakhiri hubungan kerja sebelum berakhirnya Jangka Waktu Kerja, maka Pihak yang mengakhiri hubungan kerja diwajibkan membayar ganti rugi kepada Pihak lainnya sebesar Upah dan Tunjangan tetap sampai batas waktu berakhirnya Jangka Waktu Kerja.</p>

<h3 style="text-align: center;">PASAL 12<br>DATA PEKERJA</h3>
<ol>
    <li>PEKERJA memberikan kewenangan kepada PERUSAHAAN untuk mengumpulkan dan menyimpan data PEKERJA, antara lain identitas pribadi, alamat tempat tinggal, nomor telepon, dan data lain yang diperlukan.</li>
    <li>PEKERJA menjamin bahwa seluruh informasi dan data tersebut adalah benar.</li>
    <li>Segala perubahan informasi dan data PEKERJA wajib diberitahukan kepada PERUSAHAAN selambat-lambatnya 5 (lima) hari kerja setelah adanya perubahan tersebut.</li>
</ol>

<h3 style="text-align: center;">PASAL 13<br>HAK CIPTA</h3>
<p>Seluruh hasil pekerjaan yang dihasilkan oleh PEKERJA selama masa kerja dan dalam rangka menjalankan tugasnya kepada PERUSAHAAN adalah sepenuhnya milik PERUSAHAAN.</p>

<h3 style="text-align: center;">PASAL 14<br>KERAHASIAAN</h3>
<ol>
    <li>PEKERJA wajib menjaga segala rahasia, informasi, data dan hal-hal lain yang dikategorikan sebagai informasi penting Perusahaan, baik selama masa kerja maupun setelah berakhirnya hubungan kerja.</li>
    <li>PEKERJA dilarang membuka atau memberitahukan rahasia tersebut kepada pihak manapun.</li>
    <li>Apabila PERUSAHAAN menemukan adanya kebocoran informasi dan/atau rahasia, maka PERUSAHAAN berhak mengajukan tuntutan, gugatan dan/atau permohonan ganti rugi sesuai ketentuan hukum yang berlaku.</li>
</ol>

<h3 style="text-align: center;">PASAL 15<br>ATURAN TAMBAHAN</h3>
<ol>
    <li>PERJANJIAN ini tunduk kepada peraturan perundang-undangan Republik Indonesia.</li>
    <li>Para Pihak mengesampingkan Pasal 1266 dan Pasal 1267 Kitab Undang-Undang Hukum Perdata sepanjang pasal tersebut mensyaratkan persetujuan pengadilan untuk pemutusan PERJANJIAN ini.</li>
    <li>Segala perselisihan hubungan industrial yang timbul dari atau sehubungan dengan PERJANJIAN ini akan diselesaikan menurut ketentuan peraturan perundang-undangan yang berlaku.</li>
    <li>Segala sesuatu yang belum diatur dalam Perjanjian Kerja ini akan diatur dalam peraturan dan perintah kerja/instruksi dari PERUSAHAAN yang merupakan bagian tidak terpisahkan dari Perjanjian Kerja ini.</li>
    <li>Perjanjian Kerja ini merupakan kesepakatan final dan menyeluruh antara PERUSAHAAN dan PEKERJA serta menggantikan seluruh kesepakatan atau pemahaman sebelumnya baik secara lisan maupun tulisan.</li>
</ol>
<p>Demikian Perjanjian Kerja ini dibuat dalam keadaan sehat jasmani dan rohani dan tanpa ada paksaan dari pihak manapun dan mulai berlaku sejak tanggal <strong>{{tanggal_mulai_kontrak}}</strong>.</p>

<table style="width: 100%; border-collapse: collapse; margin-top: 40px;">
    <tr>
        <td style="border: 0; width: 50%; text-align: center; vertical-align: top;">
            PEKERJA/WORKER,<br>
            {{tanda_tangan_pihak_kedua}}
            <strong>{{nama_karyawan}}</strong>
        </td>
        <td style="border: 0; width: 50%; text-align: center; vertical-align: top;">
            PT VIRTUE DRAGON NICKEL INDUSTRY,<br>
            {{tanda_tangan_pihak_pertama}}
            <strong>AHMAD SAEKUZEN</strong><br>
            HRD MANAGER
        </td>
    </tr>
</table>
HTML;
    }
};
