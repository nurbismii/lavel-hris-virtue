<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use App\Models\WarningLetterRequest;

class WarningLetterRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasMenuAccess('surat_peringatan');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, WarningLetterRequest $letter): bool
    {
        return $this->viewAny($user) && ($user->canAccessAllEmployees()
            || $user->applyEmployeeScope(Employee::query())->where('nik', $letter->nik)->exists());
    }

    public function review(User $user, WarningLetterRequest $letter): bool
    {
        return $this->view($user, $letter) && $user->hasRole(['Super Admin', 'HR']);
    }

    public function manageVerification(User $user, WarningLetterRequest $letter): bool
    {
        return $this->view($user, $letter)
            && $letter->status === WarningLetterRequest::APPROVED
            && $user->hasRole(['Super Admin', 'HR']);
    }
}
