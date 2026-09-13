<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>{{ $snapshot['number'] }}</title>
<style>
    @font-face { font-family: 'SpNotoSansSC'; font-style: normal; font-weight: normal; src: url("{{ 'file://' . str_replace('\\', '/', storage_path('fonts/NotoSansSC-Regular.ttf')) }}") format('truetype'); }
    @font-face { font-family: 'SpNotoSansSC'; font-style: normal; font-weight: bold; src: url("{{ 'file://' . str_replace('\\', '/', storage_path('fonts/NotoSansSC-Regular.ttf')) }}") format('truetype'); }
    @page { margin: 35px 60px 74px; }
    body { font-family: 'DejaVu Serif', 'SpNotoSansSC', serif; font-size: 10.5pt; line-height: 1.2; color: #111; }
    .brand { text-align: right; margin-bottom: 15px; font-family: Arial, sans-serif; white-space: nowrap; }
    .brand-mark { width: 31px; height: 31px; vertical-align: middle; margin-right: 3px; }
    .brand-name { display: inline-block; vertical-align: middle; font-size: 29pt; line-height: 1; font-weight: bold; letter-spacing: -1.5px; }
    .brand-vd { color: #FF0000; } .brand-ni { color: #595959; }
    table { border-collapse: collapse; width: 100%; }
    td { vertical-align: top; padding: 1px 0; }
    .label { width: 185px; } .colon { width: 12px; }
    p { margin: 8px 0 5px; page-break-inside: avoid; }
    .description { white-space: pre-wrap; overflow-wrap: break-word; word-wrap: break-word; margin: 10px 0 14px; }
    .signature-block { page-break-inside: avoid; margin-top: 12px; }
    .signature-block td { text-align: center; width: 50%; }
    .signature-space { height: 108px; }
    .verification-qr { width: 92px; height: 92px; }
    .verification-label { margin-top: 1px; font-family: Arial, sans-serif; font-size: 5.5pt; line-height: 1.15; color: #444; }
    .reporter { font-size: 8px; color: #555; margin-top: 16px; }
    .letter-footer { position: fixed; z-index: 9999; left: 0; right: 0; bottom: -55px; height: 42px; font-family: Arial, sans-serif; font-size: 5.2pt; line-height: 1.25; color: #565A5D; }
    .letter-footer table { width: 100%; table-layout: fixed; }
    .letter-footer td { vertical-align: middle; padding: 0 4px; }
    .footer-company { width: 38%; padding-left: 0 !important; }
    .footer-company-name, .footer-website { color: #F62835; font-weight: bold; }
    .footer-address { width: 27%; }
    .footer-contact { width: 18%; white-space: nowrap; }
    .footer-website { width: 17%; padding-right: 0 !important; white-space: nowrap; text-align: right; }
</style></head><body>
@php
    $levelNumber = substr($snapshot['level'], -1);
    $chineseLevel = ['1' => '一', '2' => '二', '3' => '三'][$levelNumber];
    $title = 'SURAT PERINGATAN ' . $levelNumber;
    $dateLabel = static function ($date) { return \Carbon\Carbon::parse($date)->locale('id')->translatedFormat('j F Y'); };
    $footer = $snapshot['footer'] ?? config('warning_letters.footer');
@endphp
<div class="letter-footer">
    <table><tr>
        <td class="footer-company"><span class="footer-company-name">| {{ $snapshot['company'] }} |</span><br>{{ $footer['building'] }}</td>
        <td class="footer-address">{{ $footer['address_line_1'] }}<br>{{ $footer['address_line_2'] }}<br>{{ $footer['address_line_3'] }}</td>
        <td class="footer-contact">PH {{ $footer['phone'] }}<br>FX {{ $footer['fax'] }}</td>
        <td class="footer-website">| {{ $footer['website'] }}</td>
    </tr></table>
</div>
<div class="brand"><img class="brand-mark" src="{{ $logoMarkSrc }}" alt=""><span class="brand-name"><span class="brand-vd">VD</span><span class="brand-ni">Ni</span></span></div>
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
    <table><tr><td></td><td>{{ $snapshot['place'] }}, {{ $dateLabel($snapshot['issued_at']) }}</td></tr><tr><td>HOD</td><td>{{ $snapshot['signer_position'] }}<br>人事部经理</td></tr><tr><td class="signature-space"></td><td class="signature-space"><img class="verification-qr" src="{{ $qrCodeSrc }}" alt="QR verifikasi"><div class="verification-label">Scan untuk verifikasi<br>{{ $verificationCode }}</div></td></tr><tr><td>{{ $snapshot['hod_name'] }}</td><td>{{ $snapshot['signer_name'] }}</td></tr></table>
</div>
</body></html>
