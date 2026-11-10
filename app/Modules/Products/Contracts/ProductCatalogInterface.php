<?php

namespace App\Modules\Products\Contracts;

use Illuminate\Support\Collection;

interface ProductCatalogInterface
{
    /**
     * Get a collection of products by their IDs for checkout and pricing calculations.
     *
     * @param array<int> $productIds
     * @return Collection
     */
    public function getProductsByIds(array $productIds): Collection;
}

