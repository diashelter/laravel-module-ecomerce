<?php

declare(strict_types=1);

namespace App\Modules\Catalog\DTOs;

use App\Modules\Catalog\Enums\ProductStatus;

final readonly class ProductDTO
{
    /**
     * @param  int  $priceCents  amount in cents (129990 = R$ 1.299,90)
     * @param  list<int>  $categoryIds
     */
    public function __construct(
        public string $name,
        public int $priceCents,
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
            'price_cents' => $this->priceCents,
            'description' => $this->description,
            'image_url' => $this->imageUrl,
            'status' => $this->status,
        ];
    }
}
