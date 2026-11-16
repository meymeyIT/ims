<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Product>
 *
 * For TESTS only. Real stock always comes from stock movements; withStock()
 * sets current_stock directly just to prepare a test scenario.
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $purchase = fake()->randomFloat(2, 0.5, 200);

        return [
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'sku' => 'SKU-' . fake()->unique()->numerify('######'),
            'barcode' => fake()->unique()->ean13(),
            'purchase_price' => $purchase,
            'selling_price' => round($purchase * 1.3, 2),
            'minimum_stock' => 5,
            'is_active' => true,
        ];
    }

    public function withStock(float $quantity, float $averageCost = 1): static
    {
        return $this->state(fn () => ['current_stock' => $quantity, 'average_cost' => $averageCost]);
    }
}
