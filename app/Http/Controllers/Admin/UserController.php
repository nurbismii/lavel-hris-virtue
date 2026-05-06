<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        $users = auth()->user()
            ->applyEmployeeRelationScope(User::query()->with(['employee', 'role', 'additionalRoles']))
            ->orderBy('name')
            ->paginate(100)
            ->withQueryString();

        return view('admin.user.index', [
            'users' => $users
        ]);
    }

    public function edit($nik_karyawan)
    {
        $user = auth()->user()
            ->applyEmployeeRelationScope(User::query()->with(['employee', 'role']))
            ->where('nik_karyawan', $nik_karyawan)
            ->firstOrFail();

        return view('admin.user.edit', [
            'user' => $user
        ]);
    }

    public function update(Request $request, $nik_karyawan)
    {
        $user = auth()->user()
            ->applyEmployeeRelationScope(User::query())
            ->where('nik_karyawan', $nik_karyawan)
            ->firstOrFail();

        $validatedData = $request->validate([
            'email' => 'required|email|unique:users,email,' . $user->nik_karyawan . ',nik_karyawan',
            'status' => 'required|in:aktif,tidak aktif',
        ]);

        $user->update($validatedData);

        toast()->success('Success', 'User updated successfully.');
        return redirect()->route('user.index');
    }

    public function destroy($nik_karyawan)
    {
        $user = auth()->user()
            ->applyEmployeeRelationScope(User::query())
            ->where('nik_karyawan', $nik_karyawan)
            ->firstOrFail();

        if ((string) $user->id === (string) auth()->id()) {
            toast()->warning('Peringatan', 'Akun yang sedang digunakan tidak dapat dihapus.');
            return redirect()->route('user.index');
        }

        if ($user->hasRole('Super Admin')) {
            toast()->warning('Peringatan', 'Akun Super Admin tidak dapat dihapus dari halaman ini.');
            return redirect()->route('user.index');
        }

        $user->delete();

        toast()->success('Success', 'User deleted successfully.');
        return redirect()->route('user.index');
    }
}
