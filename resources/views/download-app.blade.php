<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Download V-People App</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0F9D58, #0066CC);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            color: #fff;
        }

        .card {
            background: #ffffff;
            color: #333;
            width: 90%;
            max-width: 420px;
            padding: 40px 30px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
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

        .btn-download {
            display: inline-block;
            padding: 14px 28px;
            background: #0F9D58;
            color: #fff;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-download:hover {
            background: #0c7c45;
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
        <!-- Ganti dengan logo kamu -->
        <img src="{{ asset('images/vpeople-logo.png') }}" class="logo" alt="V-People">

        <h2>Gunakan Aplikasi Resmi V-People</h2>

        <p>
            Untuk pengalaman terbaik dan keamanan data,
            silakan unduh aplikasi resmi V-People.
        </p>

        <a href="https://drive.google.com/file/d/1CMNecbDYhbx0HMZqBrR4YYxzeioiVXIL/view?usp=sharing"
            target="_blank"
            class="btn-download">
            Download Aplikasi
        </a>

        <div class="note">
            Pastikan mengaktifkan "Izinkan Instalasi dari Sumber Tidak Dikenal"
        </div>
    </div>

</body>

</html>