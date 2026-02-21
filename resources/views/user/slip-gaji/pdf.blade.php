<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>PT VDNI - E-Payslip</title>
    <link rel="icon" href="{{ asset('/assets/img/kaiadmin/favicon.ico') }}" type="image/x-icon" />
    <style>
        body {
            font-family: 'NotoSansSC', DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
        }

        .subtitle {
            font-size: 12px;
            color: #666;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        th,
        td {
            padding: 6px 8px;
            border: 1px solid #ddd;
        }

        th {
            background: #f5f5f5;
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .section-title {
            background: #eee;
            font-weight: bold;
        }

        .total {
            font-size: 10px;
            font-weight: bold;
            background: #e6ffe6;
        }

        @font-face {
            font-family: 'NotoSansSC';
            src: url('{{ storage_path(' fonts/NotoSansSC-Regular.ttf') }}')format('truetype');
            font-weight: normal;
            font-style: normal;
        }
    </style>
</head>

<body>

    <div class="header">
        <table width="100%" style="border:none;">
            <tr>
                <td width="25%" style="border:none;">
                    <img src="{{ public_path('assets/img/logo-company.png') }}"
                        style="width:120px;">
                </td>
                <td width="75%" style="border:none; text-align:right;">
                    <div class="title">SLIP GAJI KARYAWAN</div>
                    <div class="subtitle">
                        Periode {{ formatDateIndonesia($slip->mulai_periode) }} - {{ formatDateIndonesia($slip->akhir_periode) }}<br>
                        {{ "E-Payslip VDNI" }}
                    </div>
                </td>
            </tr>
        </table>
        <hr style="margin-top:10px; border:1px solid #ccc;">
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

    <table width="100%" style="border:none; margin-top:15px;">
        <tr>
            <td style="border:none; font-size:10px; color:#777;">
                Slip gaji ini dihasilkan secara otomatis oleh sistem.
            </td>
            <td style="border:none; font-size:10px; color:#777; text-align:right;">
                <strong>{{ $slip->karyawan->nama ?? '-' }}</strong><br>
                Bank : {{ $slip->bank_name ?? '-' }}<br>
                No Rekening : {{ $slip->bank_number ?? '-' }}
            </td>
        </tr>
    </table>

</body>

</html>