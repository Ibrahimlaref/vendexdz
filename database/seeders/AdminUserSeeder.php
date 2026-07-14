<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Store;
use App\Models\UserStore;
use App\Models\StoreAgent;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Admin User
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@vendex.com',
            'phone' => '0550111111',
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        $admin->roles()->detach();
        $admin->roles()->attach(2); // Admin

        // 2. Create Manager User
        $manager = User::create([
            'name' => 'Manager User',
            'email' => 'manager@vendex.com',
            'phone' => '0550222222',
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        $manager->roles()->detach();
        $manager->roles()->attach(3); // Manager

        // 3. Create Agent (Confirmatrice) User
        $agent = User::create([
            'name' => 'Agent User',
            'email' => 'agent@vendex.com',
            'phone' => '0550333333',
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        $agent->roles()->detach();
        $agent->roles()->attach(4); // Agent

        // 4. Create Store owned by Manager
        $store = Store::create([
            'name' => 'Main Store',
            'created_by' => $manager->id,
        ]);

        // 5. Associate Manager with Store
        UserStore::create([
            'user_id' => $manager->id,
            'store_id' => $store->id,
            'is_active' => true,
        ]);

        // 6. Associate Agent with Store
        UserStore::create([
            'user_id' => $agent->id,
            'store_id' => $store->id,
            'is_active' => true,
        ]);

        // 7. Associate Agent via StoreAgent pivot
        StoreAgent::create([
            'aid' => $agent->id,
            'sid' => $store->id,
        ]);

        // 8. Link Agent to Manager (manager_agents table)
        DB::table('manager_agents')->insert([
            'mid' => $manager->id,
            'aid' => $agent->id,
        ]);
    }
}
