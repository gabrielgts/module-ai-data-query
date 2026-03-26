<?php

namespace Gtstudio\AiDataQuery\Api\Data;

interface EntityInterface
{
    /**
     * Get entity code.
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Get entity label.
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * Get collection class path.
     *
     * @return string
     */
    public function getCollectionClass(): string;

    /**
     * Get searchable fields.
     *
     * @return array
     */
    public function getSearchableFields(): array;

    /**
     * Get filterable fields.
     *
     * @return array
     */
    public function getFilterableFields(): array;

    /**
     * Get sortable fields.
     *
     * @return array
     */
    public function getSortableFields(): array;

    /**
     * Get max results allowed.
     *
     * @return int
     */
    public function getMaxResults(): int;

    /**
     * Check if entity is queryable.
     *
     * @return bool
     */
    public function isActive(): bool;
}
