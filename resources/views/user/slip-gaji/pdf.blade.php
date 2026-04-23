<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>PT VDNI - E-Payslip</title>
    <link rel="icon" href="{{ asset('/assets/img/kaiadmin/favicon.ico') }}" type="image/x-icon" />
    <link rel="stylesheet" href="file:///{{ str_replace('\\', '/', public_path('assets/css/user-slip-gaji-pdf.css')) }}">
</head>

<body>

    <div class="header">
        <table width="100%" class="pdf-header-table">
            <tr>
                <td width="25%">
                    <img src="{{ public_path('assets/img/logo-company.png') }}" class="pdf-logo">
                </td>
                <td width="75%" class="pdf-header-meta">
                    <div class="title">SLIP GAJI KARYAWAN</div>
                    <div class="subtitle">
                        Periode {{ formatDateIndonesia($slip->mulai_periode) }} - {{ formatDateIndonesia($slip->akhir_periode) }}<br>
                        {{ "E-Payslip VDNI" }}
                    </div>
                </td>
            </tr>
        </table>
        <hr class="pdf-header-divider">
    </div>

    {{-- INFO KARYAWAN --}}
    <table>
        <tr>
            <th width="25%">NIK</th>
            <td width="25%">{{ $slip->karyawan->nik ?? '-' }}</td>
            <th width="25%">Nama</th>
            <td width="25%">{{ $slip->karyawan->nama ?? '-' }}</td>
        </tr>
        <tr>
            <th>Departemen</th>
            <td>{{ $slip->departemen ?? '-' }}</td>
            <th>Divisi</th>
            <td>{{ $slip->divisi ?? '-' }}</td>
        </tr>
        <tr>
            <th>Posisi</th>
            <td>{{ $slip->posisi ?? '-' }}</td>
            <th>Jumlah Kehadiran</th>
            <td>{{ $slip->jml_hari_kerja ?? '-' }}</td>
        </tr>
        <tr>
            <th>Hour Machine</th>
            <td>{{ $slip->jml_hour_machine ?? '-' }}</td>
            <th>Durasi SP</th>
            <td>{{ empty($slip->durasi_sp) || $slip->durasi_sp == '0000-00-00' ? '-'  : $slip->durasi_sp }}</td>
        </tr>
    </table>

    {{-- PENDAPATAN --}}
    <table>
        <tr class="section-title">
            <td colspan="2">A. PENDAPATAN</td>
        </tr>
        <tr>
            <td>Gaji Pokok</td>
            <td class="text-right">{{ number_format($slip->gaji_pokok ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Tunjangan UM</td>
            <td class="text-right">{{ number_format($slip->tunj_um ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Tunjangan Masa kerja</td>
            <td class="text-right">{{ number_format($slip->tunj_mk ?? 0, 0, ',', '.') }}</td>
        </tr>

        @if($slip->tunj_koefisien)
        <tr>
            <td>Tunjangan Koefisien</td>
            <td class="text-right">{{ number_format($slip->tunj_koefisien ?? 0, 0, ',', '.') }}</td>
        </tr>
        @endif

        @if($slip->tunj_pengawas)
        <tr>
            <td>Tunjangan Pengawas</td>
            <td class="text-right">{{ number_format($slip->tunj_pengawas ?? 0, 0, ',', '.') }}</td>
        </tr>
        @endif

        @if($slip->tunj_transport)
        <tr>
            <td>Tunjangan Transport</td>
            <td class="text-right">{{ number_format($slip->tunj_transport ?? 0, 0, ',', '.') }}</td>
        </tr>
        @endif

        @if($slip->tunj_fungsional)
        <tr>
            <td>Tunjangan Fungsional</td>
            <td class="text-right">{{ number_format($slip->tunj_fungsional ?? 0, 0, ',', '.') }}</td>
        </tr>
        @endif

        @if($slip->ot)
        <tr>
            <td>Lembur / OT</td>
            <td class="text-right">{{ number_format($slip->ot ?? 0, 0, ',', '.') }}</td>
        </tr>
        @endif

        @if($slip->hm)
        <tr>
            <td>Hour Machine</td>
            <td class="text-right">{{ number_format($slip->hm ?? 0, 0, ',', '.') }}</td>
        </tr>
        @endif

        @if($slip->rapel)
        <tr>
            <td>Rapel</td>
            <td class="text-right">{{ number_format($slip->rapel ?? 0, 0, ',', '.') }}</td>
        </tr>
        @endif

        @if($slip->insentif)
        <tr>
            <td>Insentif / Bonus</td>
            <td class="text-right">
                {{ number_format(($slip->insentif ?? 0) + ($slip->bonus ?? 0), 0, ',', '.') }}
            </td>
        </tr>
        @endif

        @if($slip->tunj_lap)
        <tr>
            <td>Tunjangan Lapangan</td>
            <td class="text-right">
                {{ number_format($slip->tunj_lap ?? '-') }}
            </td>
        </tr>
        @endif

        @if($slip->thr)
        <tr>
            <td>THR</td>
            <td class="text-right">{{ number_format((float) $slip->thr ?? 0, 0, ',', '.') }}</td>
        </tr>
        @endif
    </table>

    {{-- POTONGAN --}}
    <table>
        <tr class="section-title">
            <td colspan="2">B. POTONGAN</td>
        </tr>

        @if($slip->pot_bpjskes)
        <tr>
            <td>BPJS Kesehatan</td>
            <td class="text-right">{{ number_format($slip->pot_bpjskes ?? 0, 0, ',', '.') }}</td>
        </tr>
        @endif

        @if($slip->jht)
        <tr>
            <td>JHT</td>
            <td class="text-right">{{ number_format($slip->jht ?? 0, 0, ',', '.') }}</td>
        </tr>
        @endif

        @if($slip->jp)
        <tr>
            <td>JP</td>
            <td class="text-right">{{ number_format($slip->jp ?? 0, 0, ',', '.') }}</td>
        </tr>
        @endif

        @if($slip->unpaid_leave)
        <tr>
            <td>Unpaid Leave</td>
            <td class="text-right">{{ number_format($slip->unpaid_leave ?? 0, 0, ',', '.') }}</td>
        </tr>
        @endif

        @if($slip->deduction_pph21)
        <tr>
            <td>PPh 21</td>
            <td class="text-right">{{ number_format($slip->deduction_pph21 ?? 0, 0, ',', '.') }}</td>
        </tr>
        @endif

        @if($slip->deduction)
        <tr>
            <td>Deduction</td>
            <td class="text-right">{{ number_format($slip->deduction ?? 0, 0, ',', '.') }}</td>
        </tr>
        @endif
    </table>

    {{-- TOTAL --}}
    <table>
        <tr class="total">
            <td>Total Gaji Diterima</td>
            <td class="text-right">
                Rp {{ number_format($slip->tot_diterima ?? 0, 0, ',', '.') }}
            </td>
        </tr>
    </table>

    <table width="100%" class="pdf-footer-table">
        <tr>
            <td class="pdf-footer-note">
                Slip gaji ini dihasilkan secara otomatis oleh sistem.
            </td>
            <td class="pdf-footer-bank">
                <strong>{{ $slip->karyawan->nama ?? '-' }}</strong><br>
                Bank : {{ $slip->bank_name ?? '-' }}<br>
                No Rekening : {{ $slip->bank_number ?? '-' }}
            </td>
        </tr>
    </table>

</body>

</html>
