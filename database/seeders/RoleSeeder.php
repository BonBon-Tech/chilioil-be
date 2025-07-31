<?php
// filepath: A:\Project\ChiliOil BE\database\seeders\RoleSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run()
    {
        Role::firstOrCreate(['name' => 'admin'], ['description' => 'Administrator']);
        Role::firstOrCreate(['name' => 'staff'], ['description' => 'Staff member']);
    }
}

