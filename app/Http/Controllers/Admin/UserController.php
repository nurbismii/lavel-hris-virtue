<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function index()
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        return view('admin.user.index');
    }

    public function dataTable(Request $request)
    {
        $query = User::query()
            ->with(['employee', 'role', 'additionalRoles'])
            ->select('users.*');

        auth()->user()->applyEmployeeRelationScope($query);

        return DataTables::of($query)
            ->addColumn('nik_karyawan', fn($row) => $row->nik_karyawan ?? '-')
            ->addColumn('nama_karyawan', fn($row) => optional($row->employee)->nama_karyawan ?? $row->name)
            ->addColumn('email', fn($row) => $row->email ?? '-')
            ->addColumn('status', fn($row) => ucfirst($row->status ?? '-'))
            ->addColumn('role', fn($row) => $row->display_role_name ?? '-')
            ->addColumn('terakhir_login', fn($row) => $row->terakhir_login ?? '-')
            ->addColumn('action', function ($row) {
                $editUrl = route('user.edit', $row->nik_karyawan);
                $deleteUrl = route('user.destroy', $row->nik_karyawan);

                $editButton = '<a href="' . e($editUrl) . '" class="btn btn-sm btn-primary btn-icon-split" title="Edit user">'
                    . '<span class="icon text-white-50"><i class="fas fa-edit"></i></span>'
                    . '<span class="text">Edit</span>'
                    . '</a>';

                $deleteButton = '<a href="' . e($deleteUrl) . '" class="btn btn-danger btn-sm btn-icon-split" data-confirm-delete="true" title="Delete user">'
                    . '<span class="icon text-white-50"><i class="fas fa-trash"></i></span>'
                    . '<span class="text">Delete</span>'
                    . '</a>';

                return $editButton . ' ' . $deleteButton;
            })
            ->rawColumns(['action'])
            ->make(true);
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
