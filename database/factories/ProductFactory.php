<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->bothify('SKU-####'),
            'name' => fake()->words(2, true),
            'price' => fake()->randomFloat(2, 1, 500),
            'stock' => fake()->numberBetween(0, 100),
        ];
    }
}
