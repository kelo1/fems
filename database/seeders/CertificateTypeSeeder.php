<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CertificateType;

class CertificateTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $certificateNames = ["New Certificate", "Fire Permit", "Renewal Certificate"];

        foreach ($certificateNames as $name) {
            CertificateType::create([
                'certificate_name' => $name,
                'created_by' => 1,
                'created_by_type' => 'App\Models\FEMSAdmin',
            ]);
        }
    }
}
