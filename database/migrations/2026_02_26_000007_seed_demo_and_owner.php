<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        // Create demo company
        DB::table('companies')->insert([
            'id' => 2,
            'name' => 'Demo Company',
            'slug' => 'demo-company',
            'is_demo' => true,
            'plan' => 'pro',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Ensure 'owner' role exists
        $ownerRole = DB::table('roles')->where('name', 'owner')->first();
        if (!$ownerRole) {
            DB::table('roles')->insert([
                'name' => 'owner',
                'description' => 'Application owner with view-only access across all companies',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create demo user (admin of demo company)
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        if ($adminRole) {
            DB::table('users')->insert([
                'name' => 'Demo User',
                'email' => 'demo@example.com',
                'password' => Hash::make('demo123'),
                'role_id' => $adminRole->id,
                'company_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create default store for demo company
            DB::table('stores')->insert([
                'name' => 'Demo Company',
                'company_id' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('stores')->where('company_id', 2)->delete();
        DB::table('users')->where('email', 'demo@example.com')->delete();
        DB::table('roles')->where('name', 'owner')->delete();
        DB::table('companies')->where('id', 2)->delete();
    }
};
