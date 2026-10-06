<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    private const TYPES = [
        'Notebook', 'Monitor', 'Teclado Mecânico', 'Mouse Gamer', 'Headset', 'Smartphone',
        'Tablet', 'Smartwatch', 'Caixa de Som', 'Webcam', 'Cadeira Ergonômica', 'Luminária',
        'Console', 'Controle', 'Carregador', 'Cabo USB-C', 'SSD', 'Roteador', 'Impressora', 'Mousepad',
    ];

    private const ADJECTIVES = ['Pro', 'Max', 'Ultra', 'Lite', 'Plus', 'Air', 'Prime', 'Turbo'];

    public function definition(): array
    {
        $name = sprintf(
            '%s %s %s %s',
            fake()->randomElement(self::TYPES),
            fake()->lastName(),
            fake()->randomElement(self::ADJECTIVES),
            fake()->bothify('?###'),
        );

        return [
            'name' => $name,
            // Prices are integer cents: money never goes through float math.
            'price_cents' => fake()->numberBetween(19, 4999) * 100 + fake()->randomElement([0, 49, 90, 99]),
            'description' => fake()->paragraphs(2, true),
            'image_url' => 'https://picsum.photos/seed/'.Str::slug($name).'/600/600',
            'status' => ProductStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Inactive]);
    }

    public function withStock(int $quantity): static
    {
        return $this->has(Stock::factory()->state(['quantity' => $quantity]));
    }
}
