<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => config('portal.admin.email')],
            ['name' => 'Admin', 'password' => config('portal.admin.password')],
        );
    }
}
