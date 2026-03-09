<?php

namespace Gtstudio\AiDataQuery\Model\Data;

use Gtstudio\AiDataQuery\Api\Data\EntityInterface;

class Entity implements EntityInterface
{
    private string $code;
    private string $label;
    private string $collectionClass;
    private array $searchableFields;
    private array $filterableFields;
    private array $sortableFields;
    private int $maxResults;
    private bool $isActive;

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

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCollectionClass(): string
    {
        return $this->collectionClass;
    }

    public function getSearchableFields(): array
    {
        return $this->searchableFields;
    }

    public function getFilterableFields(): array
    {
        return $this->filterableFields;
    }

    public function getSortableFields(): array
    {
        return $this->sortableFields;
    }

    public function getMaxResults(): int
    {
        return $this->maxResults;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
