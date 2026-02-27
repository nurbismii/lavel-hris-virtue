<?php

namespace App\Http\Controllers\SearchBySecurity;

use App\Http\Controllers\Controller;
use App\Models\SearchBySecurity\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(Request $request)
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        $users = User::all();

        return view('search-by-security.user.index', compact('users'));
    }

    /**
     * Show form create user
     */
    public function create()
    {
        return view('search-by-security.user.create');
    }

    /**
     * Store new user
     */
    public function store(Request $request)
    {
        User::create([
            'name'     => $request['name'],
            'email'    => $request['email'],
            'nik'      => $request['nik'],
            'password' => Hash::make($request['password']),
            'tgl_lahir' => $request->tgl_lahir
        ]);

        toast()->success('Success', 'User created succesfully');
        return redirect()->route('search-by-security.index');
    }

    /**
     * Show form edit
     */
    public function edit($id)
    {
        $user = User::where('id', $id)->first();

        return view('search-by-security.user.edit', compact('user'));
    }

    /**
     * Update user
     */
    public function update(Request $request, $id)
    {
        $user = User::where('id', $id)->first();

        $user->update([
            'nik' => $request['nik'],
            'name' => $request['name'],
            'email' => $request['email'],
            'tgl_lahir' => $request['tgl_lahir'],
        ]);

        if (!empty($request['password'])) {
            $user->update([
                'password' => bcrypt($request['password']),
            ]);
        }

        toast()->success('Success', 'User updated succesfully');
        return redirect()->route('search-by-security.index');
    }

    /**
     * Delete user
     */
    public function destroy(User $user)
    {
        // Hindari user hapus diri sendiri
        if (auth()->id() == $user->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }

        $user->delete();

        toast()->success('Success', 'User deleted succesfully');
        return redirect()->route('search-by-security.index');
    }
}
