<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Departemen;
use App\Models\Divisi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $roles = Role::orderBy('permission_role')->get();
        $menuGroups = collect(config('access.menus', []))
            ->map(fn($menu, $key) => array_merge($menu, ['key' => $key]))
            ->groupBy('group');
        $rolePresets = config('access.roles', []);

        return view('admin.setting-role.create', compact('roles', 'menuGroups', 'rolePresets'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'permission_role' => 'required|string|max:64|unique:roles,permission_role',
            'description' => 'nullable|string',
            'status' => 'required|in:0,1',
            'menu_permissions' => 'nullable|array',
            'menu_permissions.*' => ['string', Rule::in(array_keys(config('access.menus', [])))],
        ]);

        Role::create([
            'permission_role' => $validated['permission_role'],
            'description' => $validated['description'] ?: (config('access.roles.' . Role::normalizeRoleName($validated['permission_role']) . '.description')),
            'menu_permissions' => $validated['menu_permissions'] ?? [],
            'status' => $validated['status'],
        ]);

        toast()->success('success', 'Role berhasil ditambahkan.');
        return back();
    }

    public function edit($id)
    {
        $user = User::with(['role', 'employee.divisi.departemen'])->findOrFail($id);
        $roles = Role::where('status', '1')->get();
        $departemens = Departemen::with([
            'perusahaan',
            'divisi' => fn($query) => $query->orderBy('nama_divisi'),
        ])->orderBy('departemen')->get();
        $menuGroups = collect(config('access.menus', []))
            ->map(fn($menu, $key) => array_merge($menu, ['key' => $key]))
            ->groupBy('group');
        $roleAccessMap = $roles->mapWithKeys(function ($role) {
            return [
                $role->id => [
                    'name' => $role->permission_role,
                    'normalized_name' => $role->normalized_name,
                    'scope_label' => $role->scope_label,
                    'menus' => $role->resolved_menu_permissions,
                ],
            ];
        });
        $assignedDivisis = Divisi::query()
            ->whereIn('id', $user->scopedDivisionIds())
            ->orderBy('nama_divisi')
            ->get(['id', 'nama_divisi']);

        return view('admin.setting-role.edit', compact(
            'user',
            'roles',
            'departemens',
            'menuGroups',
            'roleAccessMap',
            'assignedDivisis'
        ));
    }

    public function updateRole(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'permission_role' => ['required', 'string', 'max:64', Rule::unique('roles', 'permission_role')->ignore($role->id)],
            'description' => 'nullable|string',
            'status' => 'required|in:0,1',
            'menu_permissions' => 'nullable|array',
            'menu_permissions.*' => ['string', Rule::in(array_keys(config('access.menus', [])))],
        ]);

        $role->update([
            'permission_role' => $validated['permission_role'],
            'description' => $validated['description'] ?: (config('access.roles.' . Role::normalizeRoleName($validated['permission_role']) . '.description')),
            'menu_permissions' => $validated['menu_permissions'] ?? [],
            'status' => $validated['status'],
        ]);

        toast()->success('Success', 'Role berhasil diperbarui');
        return back();
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
            'authorized_divisi_ids' => 'nullable|array',
            'authorized_divisi_ids.*' => 'integer|exists:divisis,id',
        ]);

        $user = User::findOrFail($id);
        $role = Role::findOrFail($validated['role_id']);
        $authorizedDivisiIds = Role::normalizeRoleName($role->permission_role) === 'Admin Divisi'
            ? collect($validated['authorized_divisi_ids'] ?? [])->filter()->map(fn($id) => (int) $id)->unique()->values()->all()
            : null;

        $user->role_id = $role->id;
        $user->authorized_divisi_ids = $authorizedDivisiIds;
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
