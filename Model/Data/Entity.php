<?php

namespace Gtstudio\AiDataQuery\Model\Data;

use Gtstudio\AiDataQuery\Api\Data\EntityInterface;

class Entity implements EntityInterface
{
    /** @var string */
    private string $code;

    /** @var string */
    private string $label;

    /** @var string */
    private string $collectionClass;

    /** @var array */
    private array $searchableFields;

    /** @var array */
    private array $filterableFields;

    /** @var array */
    private array $sortableFields;

    /** @var int */
    private int $maxResults;

    /** @var bool */
    private bool $isActive;

    /**
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->code = $data['code'] ?? '';
        $this->label = $data['label'] ?? '';
        $this->collectionClass = $data['collection_class'] ?? '';
        $this->searchableFields = is_array($data['searchable_fields'] ?? false)
            ? $data['searchable_fields']
            : (is_string($data['searchable_fields'] ?? '') ? json_decode($data['searchable_fields'], true) : []);
        $this->filterableFields = is_array($data['filterable_fields'] ?? false)
            ? $data['filterable_fields']
            : (is_string($data['filterable_fields'] ?? '') ? json_decode($data['filterable_fields'], true) : []);
        $this->sortableFields = is_array($data['sortable_fields'] ?? false)
            ? $data['sortable_fields']
            : (is_string($data['sortable_fields'] ?? '') ? json_decode($data['sortable_fields'], true) : []);
        $this->maxResults = (int)($data['max_results'] ?? 100);
        $this->isActive = (bool)($data['is_active'] ?? true);
    }

    /**
     * Get entity code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Get entity label.
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get collection class path.
     *
     * @return string
     */
    public function getCollectionClass(): string
    {
        return $this->collectionClass;
    }

    /**
     * Get searchable fields.
     *
     * @return array
     */
    public function getSearchableFields(): array
    {
        return $this->searchableFields;
    }

    /**
     * Get filterable fields.
     *
     * @return array
     */
    public function getFilterableFields(): array
    {
        return $this->filterableFields;
    }

    /**
     * Get sortable fields.
     *
     * @return array
     */
    public function getSortableFields(): array
    {
        return $this->sortableFields;
    }

    /**
     * Get max results allowed.
     *
     * @return int
     */
    public function getMaxResults(): int
    {
        return $this->maxResults;
    }

    /**
     * Check if entity is queryable.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }
}
