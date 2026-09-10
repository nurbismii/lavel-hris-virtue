<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CvMakerPdfBatch extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'filters' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime',
        'expires_at' => 'datetime', 'downloaded_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function items()
    {
        return $this->hasMany(CvMakerPdfItem::class, 'batch_id');
    }
}
