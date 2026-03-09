<?php

namespace Gtstudio\AiDataQuery\Api;

interface EntityRegistryInterface
{
    /**
     * Get entity configuration by code
     *
     * @param string $code Entity code (e.g., "sales_order")
     * @return \Gtstudio\AiDataQuery\Api\Data\EntityInterface|null
     */
    public function get(string $code): ?Data\EntityInterface;

    /**
     * Get all active queryable entities
     *
     * @return \Gtstudio\AiDataQuery\Api\Data\EntityInterface[]
     */
    public function getAll(): array;

    /**
     * Check if entity code is queryable
     *
     * @param string $code
     * @return bool
     */
    public function isQueryable(string $code): bool;
}
