<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Download V-People App</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="{{ versioned_asset('assets/css/download-app.css') }}">

</head>

<body>

    <div class="card">

        <img src="{{ asset('assets/img/kaiadmin/icon-2.png') }}" class="logo">

        <h2>Gunakan Aplikasi Resmi V-People</h2>

        <p>
            Untuk pengalaman terbaik dan keamanan data,
            silakan gunakan aplikasi resmi V-People.
        </p>

        <a href="javascript:void(0)" id="openAppButton" class="btn btn-open">
            Buka Aplikasi
        </a>

        <div id="downloadArea">

            <a href="https://drive.google.com/file/d/1CMNecbDYhbx0HMZqBrR4YYxzeioiVXIL/view?usp=sharing"
                target="_blank"
                class="btn btn-download">
                Download APK
            </a>

        </div>

        <div class="note">
            Jika aplikasi belum terpasang, silakan download terlebih dahulu.
        </div>

    </div>

    <script src="{{ versioned_asset('assets/js/download-app.js') }}"></script>

</body>

</html>