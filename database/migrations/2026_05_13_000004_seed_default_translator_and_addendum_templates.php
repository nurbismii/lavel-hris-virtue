<?php

use App\Models\ContractClause;
use App\Models\ContractTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contract_templates') || !Schema::hasTable('contract_clauses')) {
            return;
        }

        $this->seedTranslatorTemplate();
        $this->seedAddendumClauses();
        $this->seedAddendumTemplate();
    }

    public function down(): void
    {
        // Preserve templates/clauses because HR may have edited the seeded content.
    }

    private function seedTranslatorTemplate(): void
    {
        $exists = DB::table('contract_templates')
            ->where('contract_type', ContractTemplate::TYPE_TRANSLATOR)
            ->where('name', 'Kontrak Translator - Draft dari Contoh')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('contract_templates')->insert([
            'contract_type' => ContractTemplate::TYPE_TRANSLATOR,
            'name' => 'Kontrak Translator - Draft dari Contoh',
            'letterhead_html' => $this->letterheadHtml(),
            'body_html' => $this->translatorBodyHtml(),
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedAddendumClauses(): void
    {
        $clauses = [
            ContractClause::KEY_CLAUSE_1 => [
                'name' => 'Klausul 1 - Adendum Pertama',
                'body_html' => '<p>Bahwa Para Pihak sebelumnya telah mengikatkan diri dalam Perjanjian Kerja Waktu Tertentu Nomor {{no_pkwt}} tertanggal {{tanggal_pkwt_dimulai}};</p>',
            ],
            ContractClause::KEY_CLAUSE_2 => [
                'name' => 'Klausul 2 - Adendum Kedua dan Seterusnya',
                'body_html' => '<p>Bahwa Para Pihak sebelumnya telah mengikatkan diri dalam Perjanjian Kerja Waktu Tertentu Nomor {{no_pkwt}} tertanggal {{tanggal_pkwt_dimulai}}, sebagaimana telah ditambahkan terakhir dengan Adendum Perjanjian Kerja Waktu Tertentu Nomor {{nomor_adendum}} tanggal {{tanggal_perpanjangan_pertama_berakhir}};</p>',
            ],
        ];

        foreach ($clauses as $key => $clause) {
            $exists = DB::table('contract_clauses')
                ->where('clause_key', $key)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('contract_clauses')->insert([
                'clause_key' => $key,
                'name' => $clause['name'],
                'body_html' => $clause['body_html'],
                'is_active' => true,
                'created_by' => null,
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedAddendumTemplate(): void
    {
        $exists = DB::table('contract_templates')
            ->where('contract_type', ContractTemplate::TYPE_ADDENDUM_PKWT)
            ->where('name', 'Adendum PKWT - VDNI Draft dari Contoh')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('contract_templates')->insert([
            'contract_type' => ContractTemplate::TYPE_ADDENDUM_PKWT,
            'name' => 'Adendum PKWT - VDNI Draft dari Contoh',
            'letterhead_html' => $this->letterheadHtml(),
            'body_html' => $this->addendumBodyHtml(),
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
            <span style="font-size: 10px;">Indonesia Stock Exchange Building Tower 1 #2802 Level 28 Jend. Sudirman Kav. 52-53 Jakarta 12190</span>
        </td>
    </tr>
</table>
<hr style="border: 0; border-top: 1px solid #111; margin: 8px 0 18px;">
HTML;
    }

    private function translatorBodyHtml(): string
    {
        return <<<'HTML'
<h2 style="text-align: center; margin-bottom: 4px;">SURAT PERJANJIAN KERJA</h2>
<p style="text-align: center; margin-top: 0;"><strong>NO. {{no_kontrak}}</strong></p>

<p>Pada hari ini, tanggal {{tanggal_mulai_kontrak}} sesuai tanggal masuk kerja, kami masing-masing yang bertanda tangan di bawah ini:</p>

<table style="width: 100%; border-collapse: collapse;">
    <tr><td style="width: 24%;">Nama</td><td style="width: 4%; text-align: center;">:</td><td>AHMAD SAEKUZEN</td></tr>
    <tr><td>Jabatan</td><td style="text-align: center;">:</td><td>HRD Manager</td></tr>
</table>
<p>Dalam hal ini bertindak untuk dan atas nama perusahaan <strong>PT VIRTUE DRAGON NICKEL INDUSTRY</strong> yang berkedudukan di Indonesia Stock Exchange Building Tower 1 #2802 Level 28 Jend. Sudirman Kav. 52-53 Jakarta 12190, yang selanjutnya dalam perjanjian kerja ini disebut <strong>PIHAK PERTAMA</strong>.</p>

<table style="width: 100%; border-collapse: collapse;">
    <tr><td style="width: 24%;">Nama</td><td style="width: 4%; text-align: center;">:</td><td>{{nama_karyawan}}</td></tr>
    <tr><td>Tempat/Tgl. Lahir</td><td style="text-align: center;">:</td><td>-</td></tr>
    <tr><td>Alamat Terakhir</td><td style="text-align: center;">:</td><td>{{alamat}}</td></tr>
    <tr><td>Alamat KTP</td><td style="text-align: center;">:</td><td>{{alamat}}</td></tr>
    <tr><td>Jabatan</td><td style="text-align: center;">:</td><td>{{jabatan}}</td></tr>
    <tr><td>Level</td><td style="text-align: center;">:</td><td>{{kode_kontrak}}</td></tr>
</table>
<p>Dalam hal ini bertindak untuk dan atas nama pribadi, yang selanjutnya dalam Perjanjian Kerja ini disebut <strong>PIHAK KEDUA</strong>.</p>

<h3>Pasal 1<br>Ketentuan Umum</h3>
<ol>
    <li>Pihak Pertama berhak menentukan batasan fungsi, tugas dan wewenang serta penetapan kerja Pihak Kedua selama perjanjian kerja ini berlaku.</li>
    <li>Pihak Kedua wajib melaksanakan fungsi, tugas dan wewenang dengan penuh tanggung jawab.</li>
    <li>Pihak Kedua bertanggung jawab kepada HOD.</li>
</ol>

<h3>Pasal 2<br>Hak dan Ketentuan</h3>
<ol>
    <li>Terhitung sejak tanggal bergabungnya Pihak Kedua, yaitu tanggal {{tanggal_mulai_kontrak}}, maka Pihak Kedua atau Calon Karyawan PT VIRTUE DRAGON NICKEL INDUSTRY menjalani masa percobaan {{durasi_kontrak}}.</li>
    <li>Pihak Kedua berkewajiban mengikuti arahan kerja yang dilaksanakan selama bekerja.</li>
    <li>Pihak Pertama berhak menentukan jenis dan tempat pelatihan kerja bagi Pihak Kedua.</li>
    <li>Pihak Kedua wajib mentaati peraturan dan disiplin kerja yang ditetapkan oleh PIHAK PERTAMA serta melaksanakan pekerjaan secara profesional dan penuh tanggung jawab.</li>
    <li>Pihak Pertama berhak untuk melaksanakan penilaian kerja kepada Pihak Kedua.</li>
    <li>Bilamana Pihak Kedua tidak memenuhi kewajiban-kewajiban tersebut, Pihak Pertama berwenang memberikan teguran dan peringatan, baik lisan maupun tulisan.</li>
    <li>Pihak Kedua berhak atas upah dari PIHAK PERTAMA sebesar {{gaji}} per bulan, all-in, tidak ada uang lembur, dan tunjangan uang makan {{uang_makan}} per hari selama masa training, serta pajak penghasilan ditanggung perusahaan.</li>
    <li>Setelah masa evaluasi terpenuhi dan dinyatakan layak maka kenaikan upah akan disesuaikan dengan standar ketentuan perusahaan.</li>
    <li>Pihak Kedua bersedia melakukan perjalanan dinas ke luar kota sesuai kebutuhan perusahaan.</li>
    <li>Pihak Kedua bersedia dipotong gajinya bilamana mangkir termasuk meninggalkan kerja tanpa izin.</li>
</ol>

<h3>Pasal 3<br>Hak-Hak Karyawan</h3>
<ol>
    <li>Pihak Kedua berhak untuk mendapatkan 2 minggu cuti roster setelah bekerja berturut-turut selama 10 minggu.</li>
    <li>Pihak Kedua berhak untuk mendapatkan pembayaran Tunjangan Hari Raya (THR) atau dihitung secara prorata.</li>
    <li>BPJS Ketenagakerjaan dan BPJS Kesehatan diberikan bagi karyawan dan keluarga karyawan yang terdaftar secara benar dan sah pada administrasi HRD.</li>
    <li>Pihak Kedua berhak mendapat akomodasi tiket pesawat pulang pergi selama periode cuti roster.</li>
</ol>

<h3>Pasal 4<br>Masa Kerja</h3>
<ol>
    <li>Masa kerja dihitung sejak Perjanjian Kerja ini ditandatangani sampai dengan waktu yang akan ditentukan kemudian oleh kedua belah pihak.</li>
    <li>Pihak Pertama dapat memutuskan hubungan kerja pada masa percobaan karena cukup petunjuk bahwa Pihak Kedua belum mampu memenuhi persyaratan untuk diangkat sebagai karyawan perusahaan, maka kepada Pihak Kedua hanya dibayarkan gaji terakhir yang menjadi haknya.</li>
</ol>

<h3>Pasal 5<br>Penutup</h3>
<ol>
    <li>Ketentuan tentang hak cuti, jam kerja, gaji, dan upah lembur diatur tersendiri dalam Peraturan Perusahaan.</li>
    <li>Dalam hal terjadi perselisihan pendapat tentang Perjanjian Kerja ini, kedua belah pihak sepakat mengupayakan penyelesaian melalui musyawarah/mufakat terlebih dahulu.</li>
    <li>Bila upaya tersebut tidak berhasil, kedua belah pihak sepakat menyelesaikan melalui Badan Arbitrase yang ditunjuk atau melalui Pengadilan Negeri setempat.</li>
    <li>Dengan ditandatangani Perjanjian Kerja ini, maka Perjanjian Kerja sebelumnya yang pernah dibuat dianggap tidak berlaku lagi/gugur.</li>
</ol>

<p>Ditandatangani di: Morosi<br>Pada tanggal: {{tanggal_berakhir_kontrak}}</p>

<table style="width: 100%; border-collapse: collapse; margin-top: 40px;">
    <tr>
        <td style="border: 0; width: 50%; text-align: center; vertical-align: top;">
            PIHAK PERTAMA<br>
            {{tanda_tangan_pihak_pertama}}
            <strong>AHMAD SAEKUZEN</strong>
        </td>
        <td style="border: 0; width: 50%; text-align: center; vertical-align: top;">
            PIHAK KEDUA<br>
            {{tanda_tangan_pihak_kedua}}
            <strong>{{nama_karyawan}}</strong>
        </td>
    </tr>
</table>
HTML;
    }

    private function addendumBodyHtml(): string
    {
        return <<<'HTML'
<h2 style="text-align: center; margin-bottom: 4px;">ADENDUM PERJANJIAN KERJA WAKTU TERTENTU</h2>
<p style="text-align: center; margin-top: 0;"><strong>{{nomor_adendum}}</strong></p>

<p>Kami yang bertanda tangan di bawah ini:</p>

<table style="width: 100%; border-collapse: collapse;">
    <tr><td style="width: 8%; text-align: center;">I.</td><td style="width: 24%;">NAMA</td><td style="width: 4%; text-align: center;">:</td><td>AHMAD SAEKUZEN</td></tr>
    <tr><td></td><td>JABATAN</td><td style="text-align: center;">:</td><td>HR SITE MANAGER</td></tr>
    <tr><td></td><td colspan="3">Dalam hal ini bertindak dalam jabatannya, dan oleh karenanya sah dan berwenang bertindak untuk dan atas nama PT VIRTUE DRAGON NICKEL INDUSTRY, yang untuk selanjutnya disebut sebagai Pihak Pertama.</td></tr>
    <tr><td style="text-align: center;">II.</td><td>NAMA</td><td style="text-align: center;">:</td><td>{{nama_karyawan}}</td></tr>
    <tr><td></td><td>JABATAN</td><td style="text-align: center;">:</td><td>{{jabatan}}</td></tr>
    <tr><td></td><td>NIK</td><td style="text-align: center;">:</td><td>{{nik}}</td></tr>
    <tr><td></td><td colspan="3">Dalam hal ini bertindak untuk dirinya sendiri, yang untuk selanjutnya disebut sebagai Pihak Kedua.</td></tr>
    <tr><td></td><td colspan="3">Pihak Pertama dan Pihak Kedua untuk secara bersama-sama disebut sebagai Para Pihak.</td></tr>
</table>

<p>Para Pihak dengan ini terlebih dahulu menerangkan hal-hal sebagai berikut:</p>
<ol>
    <li>{{klausul}}</li>
    <li>Bahwa Para Pihak sepakat untuk memperpanjang jangka waktu Perjanjian Kerja Waktu Tertentu sebagaimana dimaksud dalam Pasal 3 ayat (1) selama {{durasi_perpanjangan_pertama}}, dari yang semula berlaku sejak {{tanggal_mulai_kontrak}} sampai dengan {{tanggal_berakhir_kontrak}}, menjadi berlaku sampai dengan {{tanggal_perpanjangan_pertama_berakhir}};</li>
    <li>Upah pokok yang berlaku bagi Pihak Kedua adalah sebesar {{upah_terbaru}} per bulan sesuai dengan peraturan dan ketentuan yang berlaku.</li>
</ol>

<p>Sehubungan dengan hal-hal tersebut di atas, Para Pihak dengan ini telah bersepakat untuk membuat dan menandatangani Adendum Perjanjian Kerja Waktu Tertentu Nomor <strong>{{nomor_adendum}}</strong> (yang untuk selanjutnya disebut sebagai Adendum), dengan ketentuan sebagai berikut:</p>
<ol>
    <li>Adendum ini mulai berlaku pada tanggal berakhirnya Perjanjian Kerja Waktu Tertentu dan/atau Adendum terakhir;</li>
    <li>Adendum ini merupakan satu kesatuan dan bagian yang tidak terpisahkan dari Perjanjian Kerja Waktu Tertentu, dan oleh karena itu memiliki kekuatan hukum yang sama dengan Perjanjian Kerja Waktu Tertentu;</li>
    <li>Ketentuan-ketentuan lainnya yang diatur dalam Perjanjian Kerja Waktu Tertentu namun tidak ditentukan lain dalam Adendum ini tetap berlaku dan mengikat Para Pihak.</li>
</ol>

<p>Demikian Adendum ini dibuat dan ditandatangani oleh Para Pihak dalam keadaan sadar, sehat jasmani dan rohani, serta tanpa adanya tekanan atau paksaan dari pihak manapun. Adendum ini dibuat dalam rangkap dua, yang kesemuanya mempunyai kekuatan hukum dan pembuktian yang sama.</p>

<table style="width: 100%; border-collapse: collapse; margin-top: 28px;">
    <tr>
        <td style="border: 0; width: 50%;">Morosi,</td>
        <td style="border: 0;">{{tanggal_perpanjangan_pertama_berakhir}}</td>
    </tr>
</table>

<table style="width: 100%; border-collapse: collapse; margin-top: 36px;">
    <tr>
        <td style="border: 0; width: 50%; text-align: center; vertical-align: top;">
            PIHAK KEDUA<br>
            {{tanda_tangan_pihak_kedua}}
            <strong>{{nama_karyawan}}</strong>
        </td>
        <td style="border: 0; width: 50%; text-align: center; vertical-align: top;">
            PIHAK PERTAMA<br>
            {{tanda_tangan_pihak_pertama}}
            <strong>AHMAD SAEKUZEN</strong>
        </td>
    </tr>
</table>
HTML;
    }
};
