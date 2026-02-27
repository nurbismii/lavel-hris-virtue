<?php

namespace App\Models\SearchBySecurity;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $connection = 'search_by_security';
    protected $table = 'users';

    protected $guarded = [];

    public function searchLogs()
    {
        return $this->hasMany(SearchLog::class);
    }
}
