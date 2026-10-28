<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Email</title>
</head>

<body style="margin:0; font-family: Arial, sans-serif; background: #f4f6f9; padding: 16px;">
    <div style="max-width: 600px; margin: auto; background: white; padding: 24px; border-radius: 8px; word-wrap:break-word; overflow-wrap:anywhere;">

        <h2 style="color: #0d6efd;">V-People HRIS</h2>

        <p>Halo {{ $user->name }},</p>

        <p>
            Terima kasih telah mendaftar.
            Klik tombol berikut untuk mengaktifkan akun Anda:
        </p>

        <p style="text-align:center; margin:30px 0;">
            <a href="{{ $url }}"
                style="background:#0d6efd; color:white; padding:12px 25px; text-decoration:none; border-radius:6px; display:inline-block;">
                Verifikasi Email
            </a>
        </p>

        <p>Jika Anda tidak merasa membuat akun, abaikan email ini.</p>

        <hr>
        <small>© {{ date('Y') }} PT Virtue Dragon Nickel Industry</small>
    </div>
</body>

</html>
