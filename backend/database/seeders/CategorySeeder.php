<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public const NAMES = [
        'Eletrônicos',
        'Informática',
        'Celulares',
        'Acessórios',
        'Casa',
        'Escritório',
        'Games',
        'Periféricos',
    ];

    public function run(): void
    {
        foreach (self::NAMES as $name) {
            Category::query()->firstOrCreate(['slug' => Category::slugFor($name)], ['name' => $name]);
        }
    }
}
