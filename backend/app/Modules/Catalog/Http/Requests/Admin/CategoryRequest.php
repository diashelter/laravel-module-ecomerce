<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\DTOs\CategoryDTO;
use App\Modules\Catalog\Models\Category;
use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Used for both create and update. The slug is derived from the name and must be unique.
 */
class CategoryRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Category::slugFor((string) $this->input('name', ''))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', Rule::unique('categories', 'slug')->ignore($this->route('category'))],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'Já existe uma categoria com este nome (slug ":input").',
            'slug.required' => 'O nome precisa conter letras ou números para gerar o slug.',
        ];
    }

    public function toDto(): CategoryDTO
    {
        return new CategoryDTO($this->validated('name'));
    }
}
