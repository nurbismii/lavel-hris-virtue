<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Verifikasi Surat Peringatan</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #eef1f4; color: #20242a; font-family: Arial, sans-serif; padding: 28px 16px; }
        .sheet { position: relative; overflow: hidden; max-width: 760px; min-height: 860px; margin: auto; background: #fff; border-radius: 16px; box-shadow: 0 12px 36px rgba(17,24,39,.12); padding: 42px; }
        .sheet::before { content: 'DOKUMEN VERIFIKASI'; position: absolute; top: 46%; left: -8%; width: 116%; transform: rotate(-28deg); color: rgba(110,118,128,.075); font-size: 54px; font-weight: 800; letter-spacing: 6px; text-align: center; pointer-events: none; }
        .brand { display: flex; justify-content: space-between; align-items: center; gap: 20px; padding-bottom: 24px; border-bottom: 2px solid #ed1c24; }
        .brand img { width: 44px; height: 44px; }
        .brand-name { font-size: 26px; font-weight: 800; color: #ed1c24; }
        .brand-name span { color: #595959; }
        .eyebrow { color: #6b7280; font-size: 12px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; }
        .status { display: inline-flex; align-items: center; gap: 8px; margin: 28px 0 10px; padding: 9px 14px; border-radius: 999px; font-size: 14px; font-weight: 700; }
        .status-valid { background: #dcfce7; color: #166534; }
        .status-expired { background: #fef3c7; color: #92400e; }
        .status-revoked, .status-invalid, .status-mismatch { background: #fee2e2; color: #991b1b; }
        h1 { margin: 8px 0; font-size: 28px; }
        .lead { color: #59616d; line-height: 1.6; margin: 0 0 28px; }
        dl { position: relative; display: grid; grid-template-columns: 1fr 1fr; gap: 18px 28px; margin: 0; }
        dt { margin-bottom: 5px; color: #6b7280; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        dd { margin: 0; font-size: 15px; font-weight: 600; overflow-wrap: anywhere; }
        .hash { margin-top: 28px; padding: 15px; border: 1px solid #e5e7eb; border-radius: 10px; background: #f8fafc; color: #4b5563; font-family: monospace; font-size: 12px; overflow-wrap: anywhere; }
        .privacy { margin-top: 26px; padding-top: 18px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 12px; line-height: 1.5; }
        @media (max-width: 600px) { .sheet { padding: 28px 22px; min-height: 760px; } dl { grid-template-columns: 1fr; } h1 { font-size: 23px; } .sheet::before { font-size: 34px; } }
        @media print { body { display: none !important; } }
    </style>
</head>
<body>
<main class="sheet">
    <header class="brand">
        <div><div class="eyebrow">Portal Verifikasi Dokumen</div><div class="brand-name">VD<span>Ni</span></div></div>
        <img src="{{ asset('assets/img/vdni-letter-logo-mark.png') }}" alt="VDNI">
    </header>
    @php
        $labels = ['valid' => 'Dokumen valid', 'expired' => 'Masa berlaku berakhir', 'revoked' => 'Verifikasi dicabut', 'mismatch' => 'Integritas dokumen tidak valid', 'invalid' => 'Dokumen tidak ditemukan'];
        $messages = [
            'valid' => 'Data di bawah cocok dengan surat peringatan yang diterbitkan melalui sistem.',
            'expired' => 'Dokumen tercatat di sistem, tetapi masa berlakunya telah berakhir.',
            'revoked' => 'Dokumen tercatat, tetapi akses verifikasinya telah dicabut oleh HR.',
            'mismatch' => 'Data dokumen tidak cocok dengan hash penerbitan. Hubungi HR untuk pemeriksaan.',
            'invalid' => 'Kode QR tidak valid atau dokumen tidak tersedia untuk verifikasi.',
        ];
        $status = $verification['status'];
        $date = static fn ($value) => \Carbon\Carbon::parse($value)->locale('id')->translatedFormat('d F Y');
    @endphp
    <div class="status status-{{ $status }}">{{ $labels[$status] }}</div>
    <h1>Verifikasi Surat Peringatan</h1>
    <p class="lead">{{ $messages[$status] }}</p>
    @if(in_array($status, ['valid', 'expired', 'revoked'], true))
        <dl>
            <div><dt>Nomor surat</dt><dd>{{ $verification['number'] }}</dd></div>
            <div><dt>Level</dt><dd>{{ $verification['level'] }}</dd></div>
            <div><dt>Nama karyawan</dt><dd>{{ $verification['employee_name'] }}</dd></div>
            <div><dt>NIK</dt><dd>{{ $verification['nik'] }}</dd></div>
            <div><dt>Departemen</dt><dd>{{ $verification['department'] }}</dd></div>
            <div><dt>Tanggal terbit</dt><dd>{{ $date($verification['issued_at']) }}</dd></div>
            <div><dt>Masa berlaku</dt><dd>{{ $date($verification['valid_from']) }} – {{ $date($verification['valid_until']) }}</dd></div>
            <div><dt>Pengesah</dt><dd>{{ $verification['signer_name'] }}<br>{{ $verification['signer_position'] }}</dd></div>
        </dl>
        <div class="hash"><strong>Hash dokumen</strong><br>{{ strtoupper($verification['document_hash']) }}</div>
    @endif
    <p class="privacy">Halaman ini hanya menampilkan ringkasan verifikasi. Keterangan pelanggaran, tanda tangan, dan data pribadi lengkap tidak dipublikasikan.</p>
</main>
</body>
</html>
