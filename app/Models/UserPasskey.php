<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPasskey extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['credential_id', 'credential_hash', 'public_key'];

    protected $casts = [
        'sign_count' => 'integer',
        'backup_eligible' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
