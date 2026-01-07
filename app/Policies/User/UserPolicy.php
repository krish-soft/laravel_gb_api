<?php

namespace App\Policies\User;

use App\Enum\Module\AppModuleEnum;
use App\Models\User;
use App\Policies\AdminPolicyManager;

class UserPolicy
{
    protected AdminPolicyManager $policy;

    protected const ACCESS_MODULES = AppModuleEnum::USERS->value;

    public function __construct(AdminPo $policy)
    {
        $this->policy = $policy;
    }

    public function viewAny(User $user): bool
    {
        // Admin access
        if ($this->policy->viewList($user, self::ACCESS_MODULES)) {
            return true;
        }

        return false;
    }

    public function view(User $user, User $model): bool
    {
        // Admin access
        if ($this->policy->viewList($user, self::ACCESS_MODULES)) {
            return true;
        }

        // Normal user → self only
        return $user->id === $model->id;
    }

    public function create(?User $user = null): bool
    {
        // Public signup
        return true;
    }

    public function update(User $user, User $model): bool
    {
        // Admin access
        if ($this->policy->update($user, self::ACCESS_MODULES)) {
            return true;
        }

        // Normal user → self only
        return $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        // Admin only
        return $this->policy->delete($user, self::ACCESS_MODULES);
    }

    public function restore(User $user, User $model): bool
    {
        // Admin only
        return $this->policy->restore($user, self::ACCESS_MODULES);
    }

    public function forceDelete(User $user, User $model): bool
    {
        // Admin only
        return $this->policy->forceDelete($user, self::ACCESS_MODULES);
    }
}
