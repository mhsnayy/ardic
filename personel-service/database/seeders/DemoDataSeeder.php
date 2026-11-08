<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Personnel;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
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
    }
}
