<?php

declare(strict_types=1);

namespace Modules\Inventory\Console;

use Illuminate\Console\Command;
use Modules\Inventory\Core\UseCases\RepublishStocksUseCase;
use Modules\Shared\Exceptions\RepublicationFailedException;

class RepublishStocksCommand extends Command
{
    protected $signature = 'inventory:republish';

    protected $description = 'Grava pelo outbox a quantidade e a versão atuais de cada estoque (a reconstrução da vitrine)';

    public function handle(RepublishStocksUseCase $republish): int
    {
        try {
            $stocks = $republish->execute();
        } catch (RepublicationFailedException $failure) {
            $this->error("Republicados antes da falha: {$failure->recorded}.");
            $this->error($failure->getPrevious()?->getMessage() ?? $failure->getMessage());

            return self::FAILURE;
        }

        $this->info("Republicados: {$stocks} estoques.");

        return self::SUCCESS;
    }
}
