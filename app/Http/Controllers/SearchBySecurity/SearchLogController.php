<?php

namespace App\Http\Controllers\SearchBySecurity;

use App\Http\Controllers\Controller;
use App\Models\SearchBySecurity\SearchLog;
use Illuminate\Http\Request;

class SearchLogController extends Controller
{
    public function index()
    {
        $logs = SearchLog::with('user')->orderBy('id', 'desc')->lazy();

        return view('search-by-security.search-log.index', compact('logs'));
    }
}
