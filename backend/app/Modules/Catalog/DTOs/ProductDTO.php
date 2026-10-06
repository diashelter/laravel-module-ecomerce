<?php

declare(strict_types=1);

namespace App\Modules\Catalog\DTOs;

use App\Modules\Catalog\Enums\ProductStatus;

final readonly class ProductDTO
{
    /**
     * @param  string  $price  decimal string ("1299.90"), never a float
     * @param  list<int>  $categoryIds
     */
    public function __construct(
        public string $name,
        public string $price,
        public string $description,
        public ?string $imageUrl,
        public ProductStatus $status,
        public array $categoryIds,
    ) {}

    /**
     * Product columns only (categories are synced separately).
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'price' => $this->price,
            'description' => $this->description,
            'image_url' => $this->imageUrl,
            'status' => $this->status,
        ];
    }
}
