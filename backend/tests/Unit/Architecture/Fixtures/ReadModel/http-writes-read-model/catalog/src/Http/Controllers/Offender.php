<?php

namespace Modules\Catalog\Http\Controllers;

use Modules\Catalog\Persistence\Repositories\ProductDocumentRepository;

/** Deliberate violation: a controller of the catalog that writes into the read model. */
class Offender
{
    public function store(ProductDocumentRepository $documents): void
    {
        $documents->tombstone('id', 1);
    }
}
