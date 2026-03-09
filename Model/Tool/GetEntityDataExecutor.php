<?php

namespace Gtstudio\AiDataQuery\Model\Tool;

use Gtstudio\AiAgents\Api\ToolExecutorInterface;
use Gtstudio\AiDataQuery\Api\EntityRegistryInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Exception\LocalizedException;

class GetEntityDataExecutor implements ToolExecutorInterface
{
    private EntityRegistryInterface $entityRegistry;
    private ObjectManagerInterface $objectManager;

    public function __construct(
        EntityRegistryInterface $entityRegistry,
        ObjectManagerInterface $objectManager
    ) {
        $this->entityRegistry = $entityRegistry;
        $this->objectManager = $objectManager;
    }

    public function execute(array $inputs): mixed
    {
        $entityCode = $inputs['entity_code'] ?? null;

        if (empty($entityCode)) {
            return "Error: entity_code parameter is required.";
        }

        // Validate entity is registered and queryable
        $entity = $this->entityRegistry->get($entityCode);
        if (!$entity || !$entity->isActive()) {
            return "Error: Entity '{$entityCode}' is not queryable.";
        }

        try {
            // Validate requested fields
            $requestedFields = $inputs['fields'] ?? [];
            if (empty($requestedFields)) {
                $requestedFields = $entity->getSearchableFields();
            }
            $this->validateFields($requestedFields, $entity->getSearchableFields());

            // Validate filters
            $filters = $inputs['filters'] ?? [];
            if (!empty($filters)) {
                $this->validateFilters($filters, $entity->getFilterableFields());
            }

            // Build collection query
            $collectionClass = $entity->getCollectionClass();
            $collection = $this->objectManager->create($collectionClass);

            // Apply filters
            foreach ($filters as $field => $value) {
                if (is_array($value)) {
                    $collection->addFieldToFilter($field, $value);
                } else {
                    $collection->addFieldToFilter($field, ['eq' => $value]);
                }
            }

            // Apply sorting — validate field against declared sortable fields
            $sortableFields = $entity->getSortableFields();
            if (!empty($inputs['sort'])) {
                $sort      = $inputs['sort'];
                $sortField = $sort['field'] ?? '';
                $direction = strtoupper($sort['direction'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

                // Map common LLM aliases to actual Magento primary key column
                if (in_array($sortField, ['id', 'order_id', 'product_id', 'customer_id'], true)) {
                    $sortField = 'entity_id';
                }

                if ($sortField !== '' && in_array($sortField, $sortableFields, true)) {
                    $collection->setOrder($sortField, $direction);
                } elseif (!empty($sortableFields)) {
                    // Fall back to first declared sortable field
                    $collection->setOrder($sortableFields[0], $direction);
                }
            } elseif (!empty($sortableFields)) {
                // Default: newest first using first sortable field
                $collection->setOrder($sortableFields[0], 'DESC');
            }

            // Limit results
            $limit = min((int)($inputs['limit'] ?? 10), $entity->getMaxResults());
            $collection->setPageSize($limit);

            // Format results
            return $this->formatResults($collection, $requestedFields);
        } catch (LocalizedException $e) {
            return "Error: " . $e->getMessage();
        } catch (\Exception $e) {
            return "Error: Failed to query entity: " . $e->getMessage();
        }
    }

    private function validateFields(array $requested, array $allowed): void
    {
        $invalid = array_diff($requested, $allowed);
        if (!empty($invalid)) {
            throw new LocalizedException(
                __("Invalid fields: %1", implode(', ', $invalid))
            );
        }
    }

    private function validateFilters(array $filters, array $allowed): void
    {
        $invalidFields = array_diff(array_keys($filters), $allowed);
        if (!empty($invalidFields)) {
            throw new LocalizedException(
                __("Cannot filter by: %1", implode(', ', $invalidFields))
            );
        }
    }

    private function formatResults($collection, array $fields): string
    {
        $items = $collection->getItems();

        if (empty($items)) {
            return "No results found.";
        }

        $formatted = sprintf("Found %d results:\n\n", count($items));

        foreach ($items as $index => $item) {
            $formatted .= sprintf("%d. ", $index + 1);
            $fieldValues = [];

            foreach ($fields as $field) {
                $value = $item->getData($field);
                if ($value !== null) {
                    $fieldValues[] = sprintf("%s: %s", $field, $this->formatValue($value));
                }
            }

            $formatted .= implode(", ", $fieldValues) . "\n";
        }

        return $formatted;
    }

    private function formatValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            return json_encode($value);
        }
        if (is_numeric($value)) {
            return (string)$value;
        }

        return (string)$value;
    }
}
