<?php

namespace App\Observers\User;

use App\Enum\User\UserRoleEnum;
use App\Models\Common\Accounting\Account;
use App\Models\Common\User\UserDepot;
use App\Models\Master\Depot\MstDepot;
use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        //
        // Create account for the user
        Account::getOrCreateByOwner(
            Account::getOwnerTypeByUser($user),
            $user->id
        );


        // Default Assign Depot (Kim)
        try {

            // Get First Depot as Kim 
            $defaultDepot = MstDepot::first();
            if ($defaultDepot && !$user->hasAssignedDepot()) {
                UserDepot::create([
                    'user_id' => $user->id,
                    'depot_id' => $defaultDepot->id,
                    'is_primary' => true,
                ]);
            }
        } catch (\Exception $e) {
            // Log the error but do not fail the user creation
            \Log::error("Failed to assign default depot to user ID {$user->id}: " . $e->getMessage());
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
