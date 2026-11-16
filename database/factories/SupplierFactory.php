<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Supplier> */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'company_name' => fake()->company(),
            'contact_person' => fake()->name(),
            'phone' => '012 ' . fake()->numerify('### ###'),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->address(),
            'tax_number' => null,
            'description' => null,
            'is_active' => true,
        ];
    }
}
