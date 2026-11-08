<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Manage the single admin record: keep it in sync with config and
     * remove any other user (and their tokens), e.g. after ADMIN_EMAIL changes.
     */
    public function run(): void
    {
        $email = config('portal.admin.email');
        $attributes = ['name' => 'Admin', 'email' => $email, 'password' => config('portal.admin.password')];

        DB::transaction(function () use ($email, $attributes) {
            $admin = User::where('email', $email)->first() ?? User::orderBy('id')->first();

            if ($admin) {
                $admin->forceFill($attributes)->save();
            } else {
                $admin = User::create($attributes);
            }

            User::where('id', '!=', $admin->id)->get()->each(function (User $u) {
                $u->tokens()->delete();
                $u->delete();
            });

            // Credentials changed: old tokens of a renamed admin are not trusted.
            if ($admin->wasChanged('email')) {
                $admin->tokens()->delete();
            }
        });
    }
}
