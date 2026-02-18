<?php

namespace App\Imports;

use App\Models\Departemen;
use App\Models\Divisi;
use App\Models\employee;
use App\Models\Perusahaan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Contracts\Queue\ShouldQueue;

class ImportEmployee implements ToCollection, WithHeadingRow, WithChunkReading, WithBatchInserts, WithValidation, ShouldQueue
{
    protected $allDepartemen;
    protected $allDivisi;
    protected $allPerusahaan;
    protected $importId;
    protected $totalRows;

    public function __construct() {}

    public function collection(Collection $rows)
    {
        $this->initMapping(); // <-- tambahkan ini

        $newRows = [];
        $newNiks = [];

        foreach ($rows as $row) {
            $nik = $row['nik'] ?? null;

            // skip jika NIK kosong atau duplikat dalam 1 file
            if (empty($nik) || in_array($nik, $newNiks)) {
                continue;
            }

            $newNiks[] = $nik;

            $kelurahanId = strval($row['kelurahan_id'] ?? '');
            $provinsiId = substr($kelurahanId, 0, 2);
            $kabupatenId = substr($kelurahanId, 0, 4);
            $kecamatanId = substr($kelurahanId, 0, 7);

            $kodePerusahaan = strtolower(trim($row['area_kerja'] ?? ''));
            $perusahaanId = $this->allPerusahaan[$kodePerusahaan] ?? null;

            $namaDepartemen = strtolower(trim($row['departemen'] ?? ''));
            $departemenId = $this->allDepartemen[$perusahaanId][$namaDepartemen] ?? null;

            $divisiId = $this->allDivisi[strtolower(trim($row['divisi'] ?? ''))] ?? null;

            $newRows[] = [
                'nik' => $nik,
                'no_sk_pkwtt' => $row['no_sk_pkwtt'] ?? null,
                'nama_karyawan' => $row['nama_karyawan'] ?? null,
                'nama_ibu_kandung' => $row['nama_ibu_kandung'] ?? null,
                'nama_bapak' => $row['nama_bapak'] ?? null,
                'agama' => $row['agama'] ?? null,
                'no_ktp' => str_replace(["'", "`"], "", $row['no_ktp'] ?? ''),
                'no_kk' => str_replace(["'", "`"], "", $row['no_kk'] ?? ''),
                'kode_area_kerja' => $row['kode_area_kerja'] ?? null,
                'jenis_kelamin' => ($row['jenis_kelamin'] ?? '') == 'M 男' ? 'L' : 'P',
                'status_perkawinan' => ($row['status_perkawinan'] ?? '') == 'TK' ? 'Belum Kawin' : 'Kawin',
                'status_karyawan' => $row['status_karyawan'] ?? null,
                'tgl_resign' => $this->parseDate($row['tgl_resign'] ?? null),
                'alasan_resign' => $row['alasan_resign'] ?? null,
                'status_resign' => strtoupper($row['status_resign']) ?? null,
                'no_telp' => $row['no_telp'] ?? null,
                'tgl_lahir' => $this->parseDate($row['tgl_lahir'] ?? null),
                'provinsi_id' => $provinsiId,
                'kabupaten_id' => $kabupatenId,
                'kecamatan_id' => $kecamatanId,
                'kelurahan_id' => $kelurahanId,
                'alamat_ktp' => $row['alamat_ktp'] ?? null,
                'alamat_domisili' => $row['alamat_domisili'] ?? null,
                'rt' => $row['rt'] ?? null,
                'rw' => $row['rw'] ?? null,
                'kode_pos' => $row['kode_pos'] ?? null,
                'area_kerja' => $row['area_kerja'] ?? null,
                'golongan_darah' => $row['golongan_darah'] ?? null,
                'entry_date' => $this->parseDate($row['entry_date'] ?? null),
                'npwp' => str_replace(['-', '.'], '', $row['npwp'] ?? ''),
                'status_pajak' => $row['status_pajak'] ?? null,
                'bpjs_kesehatan' => $row['bpjs_kesehatan'] ?? null,
                'bpjs_tk' => $row['bpjs_tk'] ?? null,
                'vaksin' => $row['vaksin'] ?? null,
                'jam_kerja' => strtoupper($row['jam_kerja'] ?? ''),
                'posisi' => $row['posisi'] ?? null,
                'jabatan' => $row['jabatan'] ?? null,
                'skill' => $row['skill'] ?? null,
                'departemen_id' => $departemenId,
                'divisi_id' => $divisiId,
                'tinggi' => $row['tinggi'] ?? null,
                'berat' => $row['berat'] ?? null,
                'hobi' => $row['hobi'] ?? null,
                'no_jamsostek' => $row['no_jamsostek'] ?? null,
                'no_asuransi' => $row['no_asuransi'] ?? null,
                'no_kartu_asuransi' => $row['no_kartu_asuransi'] ?? null,
                'nama_bank' => $row['nama_bank'] ?? null,
                'no_rekening' => $row['no_rekening'] ?? null,
                'nama_instansi_pendidikan' => $row['nama_instansi_pendidikan'] ?? null,
                'pendidikan_terakhir' => $row['pendidikan_terakhir'] ?? null,
                'jurusan' => $row['jurusan'] ?? null,
                'tanggal_kelulusan' => $this->parseDate($row['tanggal_kelulusan'] ?? null),
                'tanggal_menikah' => $this->parseDate($row['tanggal_menikah'] ?? null),
                'sisa_cuti' => $row['sisa_cuti'] ?? null,
                'sisa_cuti_covid' => $row['sisa_cuti_covid'] ?? null,
            ];
        }

        if (!empty($newRows)) {
            employee::upsert($newRows, ['nik'], array_keys($newRows[0]));
        }
    }

    public function chunkSize(): int
    {
        return 200;
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function rules(): array
    {
        return [
            'nik' => 'required',
            'status_resign' => 'required',
            'vaksin' => 'nullable|in:0,1,2,3',
            'kode_area_kerja' => 'required',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'nik.required' => 'NIK karyawan harus diisi',
            'status_resign.required' => 'Status resign harus diisi',
            'vaksin.in' => 'Vaksin harus bernilai 0, 1, 2, atau 3',
            'kode_area_kerja.required' => 'Kode area kerja harus diisi',
        ];
    }

    private function parseDate($value)
    {
        try {
            return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject(intval($value)));
        } catch (\Throwable $th) {
            return null;
        }
    }

    private function initMapping()
    {
        $this->allPerusahaan = Perusahaan::pluck('id', 'kode_perusahaan')
            ->mapWithKeys(fn($id, $kode) => [strtolower(trim($kode)) => $id])
            ->toArray();

        $this->allDepartemen = Departemen::all()
            ->groupBy('perusahaan_id')
            ->map(function ($group) {
                return $group->mapWithKeys(
                    fn($item) =>
                    [strtolower(trim($item->departemen)) => $item->id]
                );
            })
            ->toArray();

        $this->allDivisi = Divisi::pluck('id', 'nama_divisi')
            ->mapWithKeys(
                fn($id, $name) =>
                [strtolower(trim($name)) => $id]
            )
            ->toArray();
    }
}
