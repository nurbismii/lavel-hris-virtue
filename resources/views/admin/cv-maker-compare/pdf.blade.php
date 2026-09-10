<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Curriculum Vitae</title>
    <style>
        @font-face {
            font-family: 'CvNotoSansSC';
            font-style: normal;
            font-weight: normal;
            src: url("{{ 'file://' . str_replace('\\', '/', storage_path('fonts/NotoSansSC-Regular.ttf')) }}") format('truetype');
        }
        /* Use the available regular CJK face for bold runs as well, so headings retain Chinese glyphs. */
        @font-face {
            font-family: 'CvNotoSansSC';
            font-style: normal;
            font-weight: bold;
            src: url("{{ 'file://' . str_replace('\\', '/', storage_path('fonts/NotoSansSC-Regular.ttf')) }}") format('truetype');
        }
        @page { margin: 34px 40px 44px; }
        body { font-family: 'DejaVu Sans', 'CvNotoSansSC', sans-serif; font-size: 9px; line-height: 1.6; color: #263449; }
        h1 { font-size: 24px; line-height: 1.25; margin: 4px 0 7px; color: #142c48; }
        h2 { font-size: 12px; color: #164b63; border-bottom: 1px solid #cad9df; padding-bottom: 5px; margin: 20px 0 9px; page-break-after: avoid; }
        .eyebrow { font-size: 8px; letter-spacing: 2px; color: #526f80; }
        .position { font-size: 12px; margin-bottom: 5px; }
        .muted { color: #5c6c7b; }
        .header { border-bottom: 3px solid #164b63; padding-bottom: 15px; }
        p { margin: 4px 0 9px; }
        .entry { margin-bottom: 12px; }
        .entry-title { font-weight: bold; font-size: 10px; page-break-after: avoid; }
        .text { white-space: pre-line; overflow-wrap: break-word; word-wrap: break-word; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        td { vertical-align: top; padding: 3px 0; word-wrap: break-word; }
        td.label { width: 27%; color: #5c6c7b; }
        ul { margin: 4px 0 10px; padding-left: 16px; }
        li { margin: 2px 0; }
        .footer { position: fixed; bottom: -25px; left: 0; right: 0; font-size: 7px; color: #667788; border-top: 1px solid #dce3e8; padding-top: 5px; }
    </style>
</head>
<body>
@php($profile = $vitae['profile'])
<div class="footer">CV Maker · NIK {{ $nik }} · Dibuat {{ $generatedAt }} · Dokumen internal HRIS</div>
<div class="header">
    <div class="eyebrow">CURRICULUM VITAE</div>
    <h1>{{ $profile['name'] ?? '-' }}</h1>
    <div class="position">{{ $profile['position'] ?? '-' }}</div>
    <div>{{ $profile['organization'] ?? '' }}</div>
    <div class="muted">NIK {{ $nik }} · {{ $profile['email'] ?? '-' }} · {{ $profile['phone'] ?? '-' }}</div>
</div>

<h2>Ringkasan Profil</h2>
<p class="text">{{ $profile['summary'] ?? '-' }}</p>

<h2>Informasi Pribadi</h2>
<table>
    @foreach(['birth' => 'Tempat, tanggal lahir', 'gender' => 'Jenis kelamin', 'marital_status' => 'Status pernikahan',
        'religion' => 'Agama', 'blood_type' => 'Golongan darah', 'address' => 'Alamat domisili',
        'ktp_address' => 'Alamat KTP', 'location' => 'Wilayah', 'entry_date' => 'Tanggal mulai bekerja'] as $key => $label)
    <tr><td class="label">{{ $label }}</td><td>{{ $profile[$key] ?? '-' }}</td></tr>
    @endforeach
</table>

<h2>Pengalaman Kerja</h2>
@foreach($vitae['experiences'] as $item)
<div class="entry">
    <div class="entry-title">{{ $item['title'] ?? '-' }}</div>
    <div>{{ $item['company'] ?? '-' }} · {{ $item['period'] ?? '-' }}</div>
    @if(!empty($item['department']) || !empty($item['division']))
    <div class="muted">{{ $item['department'] ?? '' }} {{ $item['division'] ?? '' }}</div>
    @endif
    @if(!empty($item['responsibilities']))
    <ul>@foreach($item['responsibilities'] as $text)<li class="text">{{ $text }}</li>@endforeach</ul>
    @endif
</div>
@endforeach

<h2>Pendidikan</h2>
@foreach($vitae['educations'] as $item)
<div class="entry">
    <div class="entry-title">{{ $item['title'] ?? '-' }}</div>
    <div>{{ $item['institution'] ?? '-' }} · {{ $item['year'] ?? '-' }}</div>
</div>
@endforeach

<h2>Keahlian</h2>
<table>
    <tr><td class="label">Teknis</td><td>{{ implode(', ', $profile['technical_skills'] ?? []) ?: '-' }}</td></tr>
    <tr><td class="label">Nonteknis</td><td>{{ implode(', ', $profile['non_technical_skills'] ?? []) ?: '-' }}</td></tr>
</table>

@if(!empty($vitae['certifications']))
<h2>Sertifikasi dan Pelatihan</h2>
@foreach($vitae['certifications'] as $item)
<div class="entry"><div class="entry-title">{{ $item['title'] ?? '-' }}</div>
    <div>{{ $item['issuer'] ?? '-' }} · {{ $item['period'] ?? '-' }}</div>
</div>
@endforeach
@endif

@if(!empty($vitae['organizations']))
<h2>Pengalaman Organisasi</h2>
@foreach($vitae['organizations'] as $item)
<div class="entry"><div class="entry-title">{{ $item['title'] ?? '-' }}</div>
    <div>{{ $item['role'] ?? '-' }} · {{ $item['period'] ?? '-' }}</div>
</div>
@endforeach
@endif

@if(!empty($vitae['languages']))
<h2>Bahasa</h2>
<table>@foreach($vitae['languages'] as $item)
    <tr><td class="label">{{ $item['language'] ?? '-' }}</td><td>{{ $item['level'] ?? '-' }}</td></tr>
@endforeach</table>
@endif

@if(!empty($vitae['projects']))
<h2>Proyek</h2>
@foreach($vitae['projects'] as $item)<p>{{ $item['name'] ?? '-' }} · {{ $item['year'] ?? '-' }}</p>@endforeach
@endif

@if(!empty($vitae['achievements']))
<h2>Prestasi</h2>
@foreach($vitae['achievements'] as $item)
<div class="entry"><div class="entry-title">{{ $item['field'] ?? '-' }} · {{ $item['type'] ?? '-' }}</div>
    <div>{{ $item['rank'] ?? '-' }} · {{ $item['level'] ?? '-' }} · {{ $item['period'] ?? '-' }}</div>
</div>
@endforeach
@endif

@if(!empty($profile['hobbies']) || !empty($profile['talents']))
<h2>Minat dan Bakat</h2>
<table>
    <tr><td class="label">Minat</td><td>{{ implode(', ', $profile['hobbies'] ?? []) ?: '-' }}</td></tr>
    <tr><td class="label">Bakat</td><td>{{ implode(', ', $profile['talents'] ?? []) ?: '-' }}</td></tr>
</table>
@endif
</body>
</html>
