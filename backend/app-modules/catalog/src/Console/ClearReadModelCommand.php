<?php

declare(strict_types=1);

namespace Modules\Catalog\Console;

use Illuminate\Console\Command;
use Modules\Catalog\Core\UseCases\ClearReadModelUseCase;

class ClearReadModelCommand extends Command
{
    protected $signature = 'catalog:read-model-clear';

    protected $description = 'Esvazia o modelo de leitura da vitrine (catalog_products e catalog_categories no MongoDB)';

    public function handle(ClearReadModelUseCase $clear): int
    {
        $clear->execute();

        $this->info('Modelo de leitura esvaziado: catalog_products e catalog_categories.');

        return self::SUCCESS;
    }
}
