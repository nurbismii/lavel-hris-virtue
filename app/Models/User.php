<?php

namespace App\Models;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailNotification;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'status',
        'terakhir_login',
        'nik_karyawan',
        'role_id',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'nik_karyawan', 'nik')->select(['nik', 'nama_karyawan', 'divisi_id', 'sisa_cuti', 'posisi']);
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new class extends VerifyEmailNotification {

            public function toMail($notifiable)
            {
                return (new MailMessage)
                    ->subject('Verifikasi Email Akun V-People')
                    ->greeting('Halo ' . $notifiable->name . ',')
                    ->line('Terima kasih telah mendaftar di sistem HRIS V-People.')
                    ->line('Silakan klik tombol berikut untuk mengaktifkan akun Anda.')
                    ->action(
                        'Verifikasi Email',
                        $this->verificationUrl($notifiable)
                    )
                    ->line('Jika Anda tidak merasa membuat akun, abaikan email ini.')
                    ->salutation('PT Virtue Dragon Nickel Industry');
            }
        });
    }

    public function hasRole($roles)
    {
        if (is_array($roles)) {
            return in_array($this->role->permission_role, $roles);
        }

        return $this->role->permission_role === $roles;
    }
}
