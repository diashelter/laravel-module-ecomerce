<?php

namespace Modules\Inventory\Core;

/** Deliberate violation: a class outside the catalog that names a collection of the catalog's read model. */
class Offender
{
    public function count(): int
    {
        return \DB::connection('mongodb')->getCollection('catalog_products')->countDocuments([]);
    }
}
