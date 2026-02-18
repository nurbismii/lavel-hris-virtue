@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="page-inner">

        <h4 class="fw-bold mb-4">
            <i class="fas fa-user-cog text-primary me-2"></i>
            Edit Permission Role
        </h4>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">

                <form action="{{ route('setting-role.update', $user->id) }}"
                    method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label">NIK</label>
                        <input type="text"
                            class="form-control"
                            value="{{ $user->nik_karyawan }}"
                            readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="text"
                            class="form-control"
                            value="{{ $user->email }}"
                            readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Role Permission
                        </label>

                        <select name="role_id"
                            class="form-select @error('role_id') is-invalid @enderror"
                            required>
                            <option value="">-- Pilih Role --</option>
                            @foreach($roles as $role)
                            <option value="{{ $role->id }}"
                                {{ $user->role_id == $role->id ? 'selected' : '' }}>
                                {{ $role->permission_role }}
                            </option>
                            @endforeach
                        </select>

                        @error('role_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-left">
                        <button type="submit"
                            class="btn btn-primary me-2">
                            Update Role
                        </button>

                        <a href="{{ route('setting-role.index') }}"
                            class="btn btn-secondary">
                            Kembali
                        </a>
                    </div>

                </form>

            </div>
        </div>

    </div>
</div>
@endsection