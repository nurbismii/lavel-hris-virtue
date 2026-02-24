<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    //
    public function index()
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        $user = Role::where('permission_role', 'User')->first();

        return view('admin.user.index', [
            'users' => User::where('role_id', $user->id)->get()
        ]);
    }

    public function edit($nik_karyawan)
    {
        $user = User::with('employee')->where('nik_karyawan', $nik_karyawan)->firstOrFail();

        return view('admin.user.edit', [
            'user' => $user
        ]);
    }

    public function update(Request $request, $nik_karyawan)
    {
        $user = User::where('nik_karyawan', $nik_karyawan)->firstOrFail();

        $validatedData = $request->validate([
            'email' => 'required|email|unique:users,email,' . $user->nik_karyawan . ',nik_karyawan',
            'status' => 'required|in:aktif,tidak aktif',
        ]);

        $user->update($validatedData);

        toast()->success('Success', 'User updated successfully.');
        return redirect()->route('user.index');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        toast()->success('Success', 'User deleted successfully.');
        return redirect()->route('user.index');
    }
}
