<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LicenseType;

class LicenseTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $licenseTypes = [
            ['name' => 'Ai', 'description' => 'fire fighting installation services'],
            ['name' => 'Aii', 'description' => 'sell, service refill, repair and install fire safety equipment'],
            ['name' => 'Bi', 'description' => 'fire alarm and fire fighting installation services'],
            ['name' => 'C', 'description' => 'maintenance and service fire equipment'],
            ['name' => 'D', 'description' => 'provide fire consultancy service and sell, service and install fire safety equipment'],
            ['name' => 'E', 'description' => 'import, assemble fire equipment and fire extinguishers'],
        ];

        foreach ($licenseTypes as $type) {
            LicenseType::create($type);
        }
    }
}
