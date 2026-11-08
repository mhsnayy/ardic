<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Personnel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Personnel> */
class PersonnelFactory extends Factory
{
    protected $model = Personnel::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'department_id' => Department::factory(),
        ];
    }
}
