<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(CustomerTypeSeeder::class);
        $this->call(FEMSAdminSeeder::class);
        $this->call(CorporateTypeSeeder::class);
        $this->call(LicenseTypeSeeder::class);
        $this->call(UserTypeSeeder::class);
        $this->call(ServiceProviderDeviceSeeder::class);
        $this->call(CertificateTypeSeeder::class);
    }
}
