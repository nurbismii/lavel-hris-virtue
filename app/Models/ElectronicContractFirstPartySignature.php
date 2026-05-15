<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectronicContractFirstPartySignature extends Model
{
    public const SIGNER_KEY = 'first_party';

    protected $guarded = [];

    protected $casts = [
        'signed_at' => 'datetime',
    ];
}
