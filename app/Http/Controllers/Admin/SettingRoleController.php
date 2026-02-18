<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;

class SettingRoleController extends Controller
{
    public function index()
    {
        $users = User::with('employee', 'role')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.setting-role.index', compact('users'));
    }

    public function create()
    {
        $title = 'Delete Data!';
        $text = "Are you sure you want to delete?";
        confirmDelete($title, $text);

        $roles = Role::all();

        return view('admin.setting-role.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'permission_role' => 'required|string|max:64|unique:roles,permission_role',
            'description' => 'nullable|string',
            'status' => 'required|in:0,1'
        ]);

        Role::create([
            'permission_role' => $request->permission_role,
            'description' => $request->description,
            'status' => $request->status
        ]);

        toast()->success('success', 'Role berhasil ditambahkan.');
        return back();
    }

    public function edit($id)
    {
        $user = User::with('role')->findOrFail($id);
        $roles = Role::where('status', '1')->get();

        return view('admin.setting-role.edit', compact('user', 'roles'));
    }

    public function updateRole(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $role->update([
            'permission_role' => $request->permission_role,
            'description' => $request->description,
            'status' => $request->status
        ]);

        toast()->success('Success', 'Role berhasil diperbarui');
        return back();
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id'
        ]);

        $user = User::findOrFail($id);
        $user->role_id = $request->role_id;
        $user->save();

        toast()->success('success', 'Role berhasil diperbarui.');
        return redirect()->route('setting-role.index');
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        User::where('role_id', $role->id)->update([
            'role_id' => NULL
        ]);

        $role->delete();

        toast()->success('success', 'Role berhasil dihapus.');
        return back();
    }
}
