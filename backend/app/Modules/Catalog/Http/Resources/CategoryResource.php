<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
