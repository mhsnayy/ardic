<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->syncAdmin();

        // Ornek veri yalnizca ilk kurulumda (departments bos iken) yuklenir.
        if (! Department::query()->exists()) {
            $this->call(DemoDataSeeder::class);
        }

        if (filled($token = config('app.service_token'))) {
            Artisan::call('service:token', ['name' => 'egitim-portal', '--token' => $token]);
        }
    }

    /**
     * Servis hesabi olmayan TEK bir admin kaydi config'e gore yonetilir;
     * diger (servis olmayan) kullanicilar ve token'lari silinir.
     */
    private function syncAdmin(): void
    {
        $email = (string) config('app.admin_email');
        $humans = User::query()->orderBy('id')->get()->reject(fn (User $u) => $u->isServiceAccount());

        $admin = $humans->firstWhere('email', $email) ?? $humans->first() ?? new User;

        $admin->fill(['name' => 'Admin', 'email' => $email, 'password' => config('app.admin_password')])->save();

        // E-posta degistiyse eski hesaba ait token'lar gecersiz kalmali.
        if ($admin->wasChanged('email')) {
            $admin->tokens()->delete();
        }

        foreach ($humans->reject(fn (User $u) => $u->is($admin)) as $other) {
            $other->tokens()->delete();
            $other->delete();
        }
    }
}
