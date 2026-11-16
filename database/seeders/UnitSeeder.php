<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

/** Standard measurement units. This is reference data, so it is fine in production too. */
class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['Piece', 'pcs', 'Single item'],
            ['Kilogram', 'kg', 'Weight - 1000 grams'],
            ['Gram', 'g', 'Weight'],
            ['Liter', 'L', 'Volume - 1000 milliliters'],
            ['Milliliter', 'ml', 'Volume'],
            ['Box', 'box', 'Box of items'],
            ['Bottle', 'btl', 'Bottle'],
            ['Pack', 'pack', 'Pack of items'],
            ['Carton', 'ctn', 'Carton of items'],
        ];

        foreach ($units as [$name, $abbreviation, $description]) {
            Unit::firstOrCreate(
                ['name' => $name],
                ['abbreviation' => $abbreviation, 'description' => $description, 'is_active' => true],
            );
        }
    }
}
