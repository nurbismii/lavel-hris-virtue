<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Reset Password V-People</title>
</head>

<body style="margin:0; padding:0; font-family: Arial, sans-serif; background:#f4f6f9;">

    <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
        <tr>
            <td align="center">

                <table width="500" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; overflow:hidden;">

                    {{-- Header --}}
                    <tr>
                        <td style="background:#0d6efd; padding:20px; text-align:center; color:white;">
                            <h2 style="margin:0;">V-People HRIS</h2>
                            <br>
                            <span style="font-size:12px;">
                                PT Virtue Dragon Nickel Industry
                            </span>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:30px; color:#333333;">
                            <h4>Halo {{ $user->name ?? 'User' }},</h4>

                            <p>
                                Kami menerima permintaan untuk reset password akun kamu.
                                Klik tombol di bawah untuk membuat password baru:
                            </p>

                            <p style="text-align:center; margin:30px 0;">
                                <a href="{{ $url }}"
                                    style="background:#0d6efd;
                          color:white;
                          padding:12px 25px;
                          text-decoration:none;
                          border-radius:5px;
                          display:inline-block;">
                                    Reset Password
                                </a>
                            </p>

                            <p style="font-size:14px; color:#666;">
                                Link ini akan kadaluarsa dalam 60 menit.
                                Jika kamu tidak merasa melakukan permintaan ini,
                                abaikan email ini.
                            </p>

                            <p>Terima kasih,<br>Tim HR V-People</p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background:#f1f1f1; padding:15px; text-align:center; font-size:12px; color:#777;">
                            © {{ date('Y') }} PT Virtue Dragon Nickel Industry
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>

</html>