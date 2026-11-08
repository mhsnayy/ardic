<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => config('app.admin_email')],
            ['name' => 'Admin', 'password' => config('app.admin_password')],
        );

        $departments = collect(['Bilgi Teknolojileri', 'İnsan Kaynakları', 'Finans', 'Satış'])
            ->mapWithKeys(fn (string $name) => [$name => Department::firstOrCreate(['name' => $name])]);

        $people = [
            ['Ahmet', 'Yılmaz', 'ahmet.yilmaz@example.com', 'Bilgi Teknolojileri'],
            ['Elif', 'Demir', 'elif.demir@example.com', 'Bilgi Teknolojileri'],
            ['Mehmet', 'Kaya', 'mehmet.kaya@example.com', 'Bilgi Teknolojileri'],
            ['Ayşe', 'Çelik', 'ayse.celik@example.com', 'İnsan Kaynakları'],
            ['Fatma', 'Şahin', 'fatma.sahin@example.com', 'İnsan Kaynakları'],
            ['Mustafa', 'Aydın', 'mustafa.aydin@example.com', 'İnsan Kaynakları'],
            ['Zeynep', 'Arslan', 'zeynep.arslan@example.com', 'Finans'],
            ['Can', 'Öztürk', 'can.ozturk@example.com', 'Finans'],
            ['Burak', 'Doğan', 'burak.dogan@example.com', 'Finans'],
            ['Selin', 'Koç', 'selin.koc@example.com', 'Satış'],
            ['Emre', 'Polat', 'emre.polat@example.com', 'Satış'],
            ['Deniz', 'Güneş', 'deniz.gunes@example.com', 'Satış'],
        ];

        foreach ($people as [$first, $last, $email, $department]) {
            Personnel::firstOrCreate(
                ['email' => $email],
                ['first_name' => $first, 'last_name' => $last, 'department_id' => $departments[$department]->id],
            );
        }

        if (filled($token = config('app.service_token'))) {
            Artisan::call('service:token', ['name' => 'egitim-portal', '--token' => $token]);
        }
    }
}
