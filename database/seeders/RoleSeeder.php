<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->insert([
        ['rid' => '1' , 'name' => 'super_admin'],
        ['rid' => '2' , 'name' => 'admin'],
        ['rid' => '3' , 'name' => 'manager'],
        ['rid' => '4' , 'name' => 'agent'],
        ['rid' => '5' , 'name' => 'shop'],
        ]);
    }
}
