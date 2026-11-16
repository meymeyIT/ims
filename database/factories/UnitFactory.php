<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Unit> */
class UnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()) . ' unit',
            'abbreviation' => fake()->unique()->lexify('u???'),
            'description' => null,
            'is_active' => true,
        ];
    }
}
