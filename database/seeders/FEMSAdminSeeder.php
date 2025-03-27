<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\FEMSAdmin;
use Illuminate\Support\Facades\Hash;

class FEMSAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        FEMSAdmin::create([
            'name' => config('app.femsadmin_name', 'Default Admin Name'),
            'email' => config('app.femsadmin_email', 'admin@example.com'),
            'password' => Hash::make(config('app.femsadmin_password', 'password')),       
         ]);
    }
}
