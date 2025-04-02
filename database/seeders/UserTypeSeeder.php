<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Disable foreign key checks to avoid issues during seeding
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('user_types')->delete(); // Clear the table
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Define the user types
        $userTypes = [
            'SERVICE_PROVIDER',
            'FSA_AGENT',
            'GRA_PERSONNEL',
        ];

        // Insert the user types into the table
        foreach ($userTypes as $type) {
            DB::table('user_types')->insert([
                'user_type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
