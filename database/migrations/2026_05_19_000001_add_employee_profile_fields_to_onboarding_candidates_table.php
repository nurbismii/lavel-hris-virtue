<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = [
        'nama_ibu_kandung',
        'nama_bapak',
        'agama',
        'no_kk',
        'kode_area_kerja',
        'divisi',
        'status_karyawan',
        'no_telp',
        'tanggal_lahir',
        'alamat_ktp',
        'alamat_domisili',
        'rt',
        'rw',
        'kode_pos',
        'golongan_darah',
        'npwp',
        'status_pajak',
        'bpjs_kesehatan',
        'bpjs_tk',
        'jam_kerja',
        'skill',
        'tinggi',
        'berat',
        'hobi',
        'no_jamsostek',
        'no_asuransi',
        'no_kartu_asuransi',
        'nama_bank',
        'no_rekening',
        'nama_instansi_pendidikan',
        'pendidikan_terakhir',
        'jurusan',
        'tanggal_menikah',
        'sisa_cuti',
        'sisa_cuti_covid',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('onboarding_candidates')) {
            return;
        }

        Schema::table('onboarding_candidates', function (Blueprint $table) {
            if (!Schema::hasColumn('onboarding_candidates', 'nama_ibu_kandung')) {
                $table->string('nama_ibu_kandung', 180)->nullable()->after('nama');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'nama_bapak')) {
                $table->string('nama_bapak', 180)->nullable()->after('nama_ibu_kandung');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'agama')) {
                $table->string('agama', 50)->nullable()->after('nama_bapak');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'no_kk')) {
                $table->string('no_kk', 32)->nullable()->after('no_ktp');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'kode_area_kerja')) {
                $table->string('kode_area_kerja', 50)->nullable()->after('lokasi');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'divisi')) {
                $table->string('divisi', 180)->nullable()->after('departemen');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'status_karyawan')) {
                $table->string('status_karyawan', 80)->nullable()->after('kode_area_kerja');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'no_telp')) {
                $table->string('no_telp', 30)->nullable()->after('status_pernikahan');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'tanggal_lahir')) {
                $table->date('tanggal_lahir')->nullable()->after('no_telp');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'alamat_ktp')) {
                $table->text('alamat_ktp')->nullable()->after('alamat');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'alamat_domisili')) {
                $table->text('alamat_domisili')->nullable()->after('alamat_ktp');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'rt')) {
                $table->string('rt', 10)->nullable()->after('alamat_domisili');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'rw')) {
                $table->string('rw', 10)->nullable()->after('rt');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'kode_pos')) {
                $table->string('kode_pos', 20)->nullable()->after('rw');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'golongan_darah')) {
                $table->string('golongan_darah', 10)->nullable()->after('kode_pos');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'npwp')) {
                $table->string('npwp', 50)->nullable()->after('uang_makan');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'status_pajak')) {
                $table->string('status_pajak', 50)->nullable()->after('npwp');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'bpjs_kesehatan')) {
                $table->string('bpjs_kesehatan', 50)->nullable()->after('status_pajak');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'bpjs_tk')) {
                $table->string('bpjs_tk', 50)->nullable()->after('bpjs_kesehatan');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'jam_kerja')) {
                $table->string('jam_kerja', 50)->nullable()->after('bpjs_tk');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'skill')) {
                $table->string('skill', 180)->nullable()->after('jabatan');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'tinggi')) {
                $table->string('tinggi', 20)->nullable()->after('skill');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'berat')) {
                $table->string('berat', 20)->nullable()->after('tinggi');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'hobi')) {
                $table->string('hobi', 180)->nullable()->after('berat');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'no_jamsostek')) {
                $table->string('no_jamsostek', 50)->nullable()->after('hobi');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'no_asuransi')) {
                $table->string('no_asuransi', 50)->nullable()->after('no_jamsostek');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'no_kartu_asuransi')) {
                $table->string('no_kartu_asuransi', 50)->nullable()->after('no_asuransi');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'nama_bank')) {
                $table->string('nama_bank', 120)->nullable()->after('no_kartu_asuransi');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'no_rekening')) {
                $table->string('no_rekening', 80)->nullable()->after('nama_bank');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'nama_instansi_pendidikan')) {
                $table->string('nama_instansi_pendidikan', 180)->nullable()->after('no_rekening');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'pendidikan_terakhir')) {
                $table->string('pendidikan_terakhir', 80)->nullable()->after('nama_instansi_pendidikan');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'jurusan')) {
                $table->string('jurusan', 120)->nullable()->after('pendidikan_terakhir');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'tanggal_menikah')) {
                $table->date('tanggal_menikah')->nullable()->after('jurusan');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'sisa_cuti')) {
                $table->decimal('sisa_cuti', 8, 2)->nullable()->after('tanggal_menikah');
            }
            if (!Schema::hasColumn('onboarding_candidates', 'sisa_cuti_covid')) {
                $table->decimal('sisa_cuti_covid', 8, 2)->nullable()->after('sisa_cuti');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('onboarding_candidates')) {
            return;
        }

        Schema::table('onboarding_candidates', function (Blueprint $table) {
            $columns = array_values(array_filter(
                $this->columns,
                fn(string $column) => Schema::hasColumn('onboarding_candidates', $column)
            ));

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
