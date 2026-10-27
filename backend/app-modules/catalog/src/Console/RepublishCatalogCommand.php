<?php

declare(strict_types=1);

namespace Modules\Catalog\Console;

use Illuminate\Console\Command;
use Modules\Catalog\Core\UseCases\RepublishCatalogUseCase;
use Modules\Shared\Exceptions\RepublicationFailedException;

class RepublishCatalogCommand extends Command
{
    protected $signature = 'catalog:republish';

    protected $description = 'Grava pelo outbox o estado atual de cada categoria e de cada produto (a reconstrução da vitrine)';

    public function handle(RepublishCatalogUseCase $republish): int
    {
        try {
            $recorded = $republish->execute();
        } catch (RepublicationFailedException $failure) {
            $this->error("Republicados antes da falha: {$failure->recorded}.");
            $this->error($failure->getPrevious()?->getMessage() ?? $failure->getMessage());

            return self::FAILURE;
        }

        $this->info("Republicados: {$recorded['categories']} categorias e {$recorded['products']} produtos.");

        return self::SUCCESS;
    }
}
