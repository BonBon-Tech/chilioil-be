<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\Store;

return new class extends Migration
{
    public function up(): void
    {
        // Create base roles first (so demo user can reference admin role)
        Role::firstOrCreate(['name' => 'admin'], ['description' => 'Administrator']);
        Role::firstOrCreate(['name' => 'staff'], ['description' => 'Staff member']);

        // Create demo company using Eloquent (generates UUID automatically)
        $demoCompany = Company::create([
            'name' => 'Demo Company',
            'slug' => 'demo-company',
            'is_demo' => true,
            'plan' => 'pro',
        ]);

        // Ensure 'owner' role exists using Eloquent
        $ownerRole = Role::firstOrCreate(
            ['name' => 'owner'],
            ['description' => 'Application owner with view-only access across all companies']
        );

        // Create the owner superuser (no company_id — bypasses company scope)
        User::firstOrCreate(
            ['email' => 'owner@example.com'],
            [
                'name' => 'Owner',
                'password' => Hash::make('owner123'),
                'role_id' => $ownerRole->id,
            ]
        );

        // Create demo user (admin of demo company)
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            User::create([
                'name' => 'Demo User',
                'email' => 'demo@example.com',
                'password' => Hash::make('demo123'),
                'role_id' => $adminRole->id,
                'company_id' => $demoCompany->id,
            ]);

            // Create default store for demo company
            Store::create([
                'name' => 'Demo Company',
                'company_id' => $demoCompany->id,
            ]);
        }
    }

    public function down(): void
    {
        $demoCompany = DB::table('companies')->where('slug', 'demo-company')->first();
        if ($demoCompany) {
            DB::table('stores')->where('company_id', $demoCompany->id)->delete();
            DB::table('users')->where('email', 'demo@example.com')->delete();
            DB::table('companies')->where('id', $demoCompany->id)->delete();
        }
        DB::table('users')->where('email', 'owner@example.com')->delete();
        DB::table('roles')->where('name', 'owner')->delete();
    }
};
