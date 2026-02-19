<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest\RegisterRequest;
use App\Models\Employee;
use App\Models\Role;
use App\Providers\RouteServiceProvider;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;

class RegisterController extends Controller
{
    use RegistersUsers;

    protected $redirectTo = RouteServiceProvider::HOME;

    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    public function register(RegisterRequest $request)
    {
        $role = Role::where('permission_role', 'User')->select('id')->first();

        $employee = employee::where('nik', $request->nik_karyawan)->first();

        $user = User::create([
            'id' => Uuid::uuid4()->getHex(),
            'name' => $employee->nama_karyawan,
            'nik_karyawan' => $request->nik_karyawan,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => 'tidak aktif',
            'role_id' => $role->id
        ]);

        event(new Registered($user));

        auth()->login($user);

        return redirect()->route('verification.notice');
    }
}
