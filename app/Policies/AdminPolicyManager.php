<?php

namespace App\Policies;

use App\Enum\Admin\AdminActionEnum;
use App\Enum\Admin\AdminRoleEnum;
use App\Enum\User\UserRoleEnum;
use App\Models\User;

class AdminPolicyManager
{
    /**
     * Final entry point
     */
    public function hasAccess(
        User $user,
        int $moduleCode,
        AdminActionEnum $action
    ): bool {
        // Superadmin bypass
        if ($user->role === AdminRoleEnum::SUPERADMIN->value) {
            return true;
        }

        // Only admin users allowed
        // if ($user->role !== AdminRoleEnum::ADMIN->value) {
        //     return false;
        // }
        if (in_array($user->role, [
            UserRoleEnum::BUYER->value,
            UserRoleEnum::SELLER->value,
            UserRoleEnum::DELIVERY->value,
        ], true) === false) {
            return false;
        }

        // 1️⃣ MUST have module access first
        if (! $this->hasModuleAccess($user, $moduleCode)) {
            return false;
        }

        // 2️⃣ User-type rules (business rule)
        if (! $this->isUserTypeAllowed($user, $action)) {
            return false;
        }

        // 3️⃣ Action-level permission inside module
        return $this->hasActionAccess($user, $moduleCode, $action);
    }

    /**
     * Step 1: Module access
     */
    protected function hasModuleAccess(User $user, int $moduleCode): bool
    {
        return $user->modules()
            ->where('module_code', $moduleCode)
            ->exists();
    }

    /**
     * Step 2: Action access inside module
     */
    protected function hasActionAccess(
        User $user,
        int $moduleCode,
        AdminActionEnum $action
    ): bool {
        return $user->modulePermissions()
            ->where('module_code', $moduleCode)
            ->where('action', $action->value)
            ->exists();
    }

    /**
     * Step 3: User-type restriction (optional but realistic)
     */
    protected function isUserTypeAllowed(User $user, AdminActionEnum $action): bool
    {
        return match ($user->user_type) {
            'staff' => in_array($action, [
                AdminActionEnum::VIEW_LIST,
                AdminActionEnum::STORE,
            ], true),

            'manager' => in_array($action, [
                AdminActionEnum::VIEW_LIST,
                AdminActionEnum::STORE,
                AdminActionEnum::UPDATE,
            ], true),

            'admin' => true,

            default => false,
        };
    }

    // ---- Shortcut helpers ---- //

    public function viewList(User $user, int $moduleCode): bool
    {
        return $this->hasAccess($user, $moduleCode, AdminActionEnum::VIEW_LIST);
    }

    public function store(User $user, int $moduleCode): bool
    {
        return $this->hasAccess($user, $moduleCode, AdminActionEnum::STORE);
    }

    public function update(User $user, int $moduleCode): bool
    {
        return $this->hasAccess($user, $moduleCode, AdminActionEnum::UPDATE);
    }

    public function delete(User $user, int $moduleCode): bool
    {
        return $this->hasAccess($user, $moduleCode, AdminActionEnum::DELETE);
    }

    public function restore(User $user, int $moduleCode): bool
    {
        return $this->hasAccess($user, $moduleCode, AdminActionEnum::RESTORE);
    }

    public function forceDelete(User $user, int $moduleCode): bool
    {
        return $this->hasAccess($user, $moduleCode, AdminActionEnum::FORCE_DELETE);
    }
}
