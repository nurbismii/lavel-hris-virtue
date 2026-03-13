<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Download V-People App</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0F9D58, #0066CC);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            box-sizing: border-box;
        }

        .card {
            background: #fff;
            color: #333;
            width: 100%;
            max-width: 420px;
            padding: 35px 25px;
            border-radius: 24px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        .logo {
            width: 90px;
            margin-bottom: 20px;
        }

        h2 {
            margin: 10px 0 15px;
            font-weight: 600;
        }

        p {
            font-size: 14px;
            color: #666;
            margin-bottom: 30px;
        }

        .btn {
            display: block;
            padding: 14px 28px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 12px;
        }

        .btn-open {
            background: #0066CC;
            color: white;
        }

        .btn-download {
            background: #0F9D58;
            color: white;
        }

        .note {
            margin-top: 20px;
            font-size: 12px;
            color: #888;
        }
    </style>

</head>

<body>

    <div class="card">

        <img src="{{ asset('assets/img/kaiadmin/favicon-1.png') }}" class="logo">

        <h2>Gunakan Aplikasi Resmi V-People</h2>

        <p>
            Untuk pengalaman terbaik dan keamanan data,
            silakan gunakan aplikasi resmi V-People.
        </p>

        <a href="javascript:void(0)" onclick="openApp()" class="btn btn-open">
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

    <script>
        function openApp() {

            // mencoba membuka aplikasi
            window.location = "vpeople://dashboard";

            // jika gagal buka aplikasi tampilkan tombol download
            setTimeout(function() {
                document.getElementById('downloadArea').style.display = "block";
            }, 1500);
        }

        // auto open app setelah 3 detik
        window.onload = function() {

            setTimeout(function() {
                openApp();
            }, 3000);

        }
    </script>

</body>

</html>