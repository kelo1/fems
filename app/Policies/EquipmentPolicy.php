<?php

namespace App\Policies;

use App\Models\FEMSAdmin;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EquipmentPolicy
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
     * @param  \App\Models\Equipment  $equipment
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Equipment $equipment)
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
     * @param  \App\Models\Equipment  $equipment
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Equipment $equipment)
    {
        //
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Equipment  $equipment
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Equipment $equipment)
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Equipment  $equipment
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Equipment $equipment)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Equipment  $equipment
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Equipment $equipment)
    {
        //
    }

    /**
     * Determine whether the user can generate a QR code for the equipment.
     *
     * @param  \App\Models\FEMSAdmin  $user
     * @param  \App\Models\Equipment  $equipment
     * @return mixed
     */
    public function generateQrCode(FEMSAdmin $user, Equipment $equipment)
    {
        // Only FEMSAdmin can generate a QR code
        return $user instanceof FEMSAdmin;
    }
}
