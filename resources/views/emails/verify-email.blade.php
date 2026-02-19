<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Verifikasi Email</title>
</head>

<body style="font-family: Arial; background: #f4f6f9; padding: 30px;">
    <div style="max-width: 600px; margin: auto; background: white; padding: 30px; border-radius: 8px;">

        <h2 style="color: #0d6efd;">V-People HRIS</h2>

        <p>Halo {{ $user->name }},</p>

        <p>
            Terima kasih telah mendaftar.
            Klik tombol berikut untuk mengaktifkan akun Anda:
        </p>

        <p style="text-align:center; margin:30px 0;">
            <a href="{{ $url }}"
                style="background:#0d6efd; color:white; padding:12px 25px; text-decoration:none; border-radius:6px;">
                Verifikasi Email
            </a>
        </p>

        <p>Jika Anda tidak merasa membuat akun, abaikan email ini.</p>

        <hr>
        <small>© {{ date('Y') }} PT Virtue Dragon Nickel Industry</small>
    </div>
</body>

</html>