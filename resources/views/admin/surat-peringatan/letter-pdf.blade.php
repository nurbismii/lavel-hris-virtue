<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>{{ $snapshot['number'] }}</title>
<style>
    @font-face { font-family: 'SpNotoSansSC'; font-style: normal; font-weight: normal; src: url("{{ 'file://' . str_replace('\\', '/', storage_path('fonts/NotoSansSC-Regular.ttf')) }}") format('truetype'); }
    @font-face { font-family: 'SpNotoSansSC'; font-style: normal; font-weight: bold; src: url("{{ 'file://' . str_replace('\\', '/', storage_path('fonts/NotoSansSC-Regular.ttf')) }}") format('truetype'); }
    @page { margin: 35px 60px 45px; }
    body { font-family: 'DejaVu Serif', 'SpNotoSansSC', serif; font-size: 10.5pt; line-height: 1.2; color: #111; }
    .brand { font-size: 29px; font-weight: bold; color: #18344a; text-align: right; margin-bottom: 0; }
    .company { font-size: 8px; text-align: right; margin-bottom: 15px; }
    table { border-collapse: collapse; width: 100%; }
    td { vertical-align: top; padding: 1px 0; }
    .label { width: 185px; } .colon { width: 12px; }
    p { margin: 8px 0 5px; page-break-inside: avoid; }
    .description { white-space: pre-wrap; overflow-wrap: break-word; word-wrap: break-word; margin: 10px 0 14px; }
    .signature-block { page-break-inside: avoid; margin-top: 12px; }
    .signature-block td { text-align: center; width: 50%; }
    .signature-space { height: 70px; } .signature-space img { max-height: 65px; max-width: 175px; }
    .reporter { font-size: 8px; color: #555; margin-top: 16px; }
</style></head><body>
@php
    $levelNumber = substr($snapshot['level'], -1);
    $chineseLevel = ['1' => '一', '2' => '二', '3' => '三'][$levelNumber];
    $title = 'SURAT PERINGATAN ' . $levelNumber;
    $dateLabel = static function ($date) { return \Carbon\Carbon::parse($date)->locale('id')->translatedFormat('j F Y'); };
@endphp
<div class="brand">VDNi</div><div class="company">{{ $snapshot['company'] }}</div>
<table>
    <tr><td class="label">Nomor 字母编号</td><td class="colon">:</td><td>{{ $snapshot['number'] }}</td></tr>
    <tr><td class="label">Lampiran 附件</td><td>:</td><td>-</td></tr>
    <tr><td class="label">Perihal 关于</td><td>:</td><td><strong>{{ $title }}</strong> {{ $chineseLevel }}级警告单</td></tr>
</table>
<p>Yang bertanda tangan di bawah ini 下面签名：</p>
<table><tr><td class="label">Nama 姓名</td><td class="colon">:</td><td>{{ $snapshot['signer_name'] }}</td></tr><tr><td class="label">Jabatan 岗位</td><td>:</td><td>{{ $snapshot['signer_position'] }}</td></tr></table>
<p>Dengan ini memberikan <strong>{{ $title }}</strong> kepada 特此发出{{ $chineseLevel }}级警告书：</p>
<table>
    @foreach(['name' => 'Nama 姓名', 'department' => 'Departemen 大部门', 'division' => 'Divisi 部门', 'position' => 'Jabatan 岗位', 'nik' => 'NIK 工号'] as $key => $label)
    <tr><td class="label">{{ $label }}</td><td class="colon">:</td><td>{{ $snapshot['employee'][$key] }}</td></tr>
    @endforeach
</table>
<p>Bahwa karyawan tersebut telah melakukan hal-hal sebagai berikut 该员工不当行为如下：</p>
<div class="description">{{ $snapshot['description'] }}</div>
<p>Berdasarkan pelanggaran tersebut, maka <strong>{{ $title }}</strong> langsung diberikan kepada yang bersangkutan untuk mengoreksi diri dan memperhatikan surat peringatan ini.</p>
<p>根据公司规定，此员工已经被{{ $chineseLevel }}级警告，此将{{ $chineseLevel }}级警告直接发给相关人员，以便其注意自己不当行为。</p>
<p>Surat peringatan ini berlaku dari tanggal {{ $dateLabel($snapshot['start']) }} sampai dengan {{ $dateLabel($snapshot['end']) }}.<br>本警告书于 {{ $dateLabel($snapshot['start']) }} 至 {{ $dateLabel($snapshot['end']) }} 有效。</p>
<div class="signature-block">
    <table><tr><td></td><td>{{ $snapshot['place'] }}, {{ $dateLabel($snapshot['issued_at']) }}</td></tr><tr><td>HOD</td><td>{{ $snapshot['signer_position'] }}<br>人事部经理</td></tr><tr><td class="signature-space"></td><td class="signature-space"><img src="{{ $signatureSrc }}" alt="Tanda tangan pihak HR"></td></tr><tr><td>{{ $snapshot['hod_name'] }}</td><td>{{ $snapshot['signer_name'] }}</td></tr></table>
    <div class="reporter">Pelapor: {{ $snapshot['reporter'] }}</div>
</div>
</body></html>
