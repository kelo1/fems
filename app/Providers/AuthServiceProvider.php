<?php

namespace App\Providers;

use App\Models\Equipment;
use App\Policies\EquipmentPolicy;
use App\Models\FEMSAdmin;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as BaseAuthServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends BaseAuthServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Equipment::class => EquipmentPolicy::class,
        FEMSAdmin::class => UserPolicy::class,
        
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        Gate::define('update-isActive', [UserPolicy::class, 'updateIsActive']);
        Gate::define('update-user', [UserPolicy::class, 'update']);
        Gate::define('delete-user', [UserPolicy::class, 'delete']);
   
    }
}
