<?php

namespace App\Policies;

use App\Models\FEMSAdmin;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\FEMSAdmin  $fEMSAdmin
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, FEMSAdmin $fEMSAdmin)
    {
        //
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        //
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\FEMSAdmin  $fEMSAdmin
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(FEMSAdmin $admin)
    {
        return $admin instanceof FEMSAdmin; // Only FEMSAdmin users can update
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\FEMSAdmin  $fEMSAdmin
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(FEMSAdmin $admin)
    {
        return $admin instanceof FEMSAdmin; // Only FEMSAdmin users can delete
    }

    public function updateIsActive(FEMSAdmin $admin)
    {
        return $admin instanceof FEMSAdmin; // Only FEMSAdmin users can update
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\FEMSAdmin  $fEMSAdmin
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, FEMSAdmin $admin)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\FEMSAdmin  $fEMSAdmin
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, FEMSAdmin $fEMSAdmin)
    {
        //
    }
}
