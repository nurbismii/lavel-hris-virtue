<?php

namespace App\Models\SearchBySecurity;

use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    protected $connection = 'search_by_security';
    protected $table = 'search_logs';

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
