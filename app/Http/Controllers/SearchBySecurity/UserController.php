<?php

namespace App\Http\Controllers\SearchBySecurity;

use App\Http\Controllers\Controller;
use App\Models\SearchBySecurity\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

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

        $users = User::orderBy('name')
            ->paginate(100)
            ->withQueryString();

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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('search_by_security.users', 'email')],
            'nik' => ['required', 'string', 'max:50', Rule::unique('search_by_security.users', 'nik')],
            'password' => ['required', 'string', 'min:8'],
            'tgl_lahir' => ['required', 'date'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'nik' => $validated['nik'],
            'password' => Hash::make($validated['password']),
            'tgl_lahir' => $validated['tgl_lahir'],
        ]);

        toast()->success('Success', 'User created succesfully');
        return redirect()->route('search-by-security.index');
    }

    /**
     * Show form edit
     */
    public function edit($id)
    {
        $user = User::where('id', $id)->firstOrFail();

        return view('search-by-security.user.edit', compact('user'));
    }

    /**
     * Update user
     */
    public function update(Request $request, $id)
    {
        $user = User::where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('search_by_security.users', 'email')->ignore($user->id)],
            'nik' => ['required', 'string', 'max:50', Rule::unique('search_by_security.users', 'nik')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'tgl_lahir' => ['required', 'date'],
        ]);

        $user->update([
            'nik' => $validated['nik'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'tgl_lahir' => $validated['tgl_lahir'],
        ]);

        if (!empty($validated['password'])) {
            $user->update([
                'password' => Hash::make($validated['password']),
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
