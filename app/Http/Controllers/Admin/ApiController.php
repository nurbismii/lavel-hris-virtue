<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Airports;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function getAirport(Request $request)
    {
        if ($request->has('q')) {
            $search = $request->q;
            $data = Airports::select('*')->where('name', 'like', '%' . $search . '%')->orWhere('municipality', 'like', '%' . $search . '%')->limit(20)->get();
            return response()->json($data);
        }
    }
}
