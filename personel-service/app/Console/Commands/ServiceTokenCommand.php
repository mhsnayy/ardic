<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

#[Signature('service:token {name=egitim-portal} {--token= : Kaydedilecek duz token (verilmezse uretilir)}')]
#[Description('Servis kullanicisi icin personnel:read yetkili, suresiz token kaydeder')]
class ServiceTokenCommand extends Command
{
    public function handle(): int
    {
        $name = (string) $this->argument('name');

        $user = User::firstOrCreate(
            ['email' => $name.User::SERVICE_EMAIL_DOMAIN],
            ['name' => $name, 'password' => Str::random(64)],
        );

        $plain = $this->option('token');
        $generated = blank($plain);
        $plain = $generated ? Str::random(40) : (string) $plain;
        $hash = hash('sha256', $plain);

        if (PersonalAccessToken::where('token', $hash)->exists()) {
            $this->info('Token zaten kayitli, bir sey yapilmadi.');

            return self::SUCCESS;
        }

        // Ayni isimli eski tokenlar gecersiz olsun.
        $user->tokens()->where('name', $name)->delete();

        $user->tokens()->create([
            'name' => $name,
            'token' => $hash,
            'abilities' => ['personnel:read'],
            'expires_at' => null,
        ]);

        $this->info("Servis token'i kaydedildi: {$name}");
        if ($generated) {
            $this->line($plain);
        }

        return self::SUCCESS;
    }
}
