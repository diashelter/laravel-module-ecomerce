<?php

declare(strict_types=1);

namespace Modules\Catalog\Console;

use Illuminate\Console\Command;
use Modules\Catalog\Persistence\ReadModel\ReadModelStore;

class ClearReadModelCommand extends Command
{
    protected $signature = 'catalog:read-model-clear';

    protected $description = 'Esvazia o modelo de leitura da vitrine (catalog_products e catalog_categories no MongoDB)';

    public function handle(ReadModelStore $store): int
    {
        $store->clear();

        $this->info('Modelo de leitura esvaziado: catalog_products e catalog_categories.');

        return self::SUCCESS;
    }
}
