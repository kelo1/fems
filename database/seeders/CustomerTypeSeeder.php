<?php

namespace Database\Seeders;
use App\Models\CustomerType;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
       CustomerType::truncate();

       CustomerType::create([
           'name' => 'INDIVIDUAL',
       ]);

       CustomerType::create([
        'name' => 'CORPORATE',
       ]);
    }
}
