<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $contract->display_number }}</title>
    <style>
        @page {
            margin: 28px 36px;
        }

        body {
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.5;
        }

        .contract-letterhead {
            margin-bottom: 18px;
        }

        .contract-body table,
        .contract-letterhead table {
            border-collapse: collapse;
            width: 100%;
        }

        .contract-body td,
        .contract-body th,
        .contract-letterhead td,
        .contract-letterhead th {
            border: 1px solid #d1d5db;
            padding: 6px;
            vertical-align: top;
        }

        .contract-body img,
        .contract-letterhead img {
            max-width: 100%;
        }

        .contract-signature-slot {
            display: block;
            height: 86px;
            line-height: normal;
            margin: 4px 0;
            text-align: center;
        }

        .contract-signature-box {
            border: 0 !important;
            border-collapse: collapse;
            height: 86px;
            margin: 0;
            width: 100%;
        }

        .contract-signature-box td {
            border: 0 !important;
            height: 86px;
            padding: 0 !important;
            text-align: center;
            vertical-align: middle;
        }

        .contract-signature-image {
            height: 76px;
            max-width: 220px;
            vertical-align: middle;
        }

        .document-hash {
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 8px;
            margin-top: 16px;
            padding-top: 8px;
            word-break: break-all;
        }
    </style>
</head>
<body>
    {!! $html !!}

    @if($signature || $contract->first_party_signature_path)
        <div class="document-hash">
            @if($contract->first_party_signature_path)
                Pihak Pertama: {{ optional($contract->first_party_signed_at)->format('d M Y H:i:s') ?: '-' }};
            @endif
            @if($signature)
                Pihak Kedua: {{ optional($signature->signed_at)->format('d M Y H:i:s') }};
                IP: {{ $signature->ip_address ?: '-' }};
            @endif
            Hash dokumen: {{ $contract->pdf_hash ?: optional($signature)->document_hash ?: 'tersimpan setelah PDF final dibuat' }}
        </div>
    @endif
</body>
</html>
