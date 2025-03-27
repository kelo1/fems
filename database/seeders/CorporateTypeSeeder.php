<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CorporateType;
use Illuminate\Support\Facades\DB;

class CorporateTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
       // CorporateType::truncate();
       DB::statement('SET FOREIGN_KEY_CHECKS=0;');
       DB::table('corporate_types')->delete(); // Use delete() instead of truncate()
       DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $CorpateTypes = [
            'Agriculture & Forestry',
            'Automotive & Transport',
            'Banking & Finance',
            'Construction & Real Estate',
            'Consulting & Business Services',
            'Education & Training',
            'Energy (Oil, Gas & Renewable)',
            'Engineering & Manufacturing',
            'Entertainment & Media',
            'Environmental Services',
            'Fashion & Textile',
            'Food & Beverage',
            'Government & Public Sector',
            'Healthcare & Pharmaceuticals',
            'Hospitality & Tourism',
            'Human Resources & Recruitment',
            'Information Technology & Software',
            'Insurance',
            'Legal Services',
            'Logistics & Supply Chain',
            'Maritime & Shipping',
            'Marketing & Advertising',
            'Mining & Metals',
            'Non-Profit & NGOs',
            'Oil & Gas',
            'Retail & E-Commerce',
            'Science & Research',
            'Security & Defense',
            'Sports & Recreation',
            'Telecommunications',
            'Transportation & Logistics',
            'Utilities (Electricity, Water, Waste Management)',
        ];

        foreach ($CorpateTypes as $type) {
            DB::table('corporate_types')->insert([
                'name' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
