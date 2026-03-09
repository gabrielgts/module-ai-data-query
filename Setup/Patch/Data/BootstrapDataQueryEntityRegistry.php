<?php

declare(strict_types=1);

namespace Gtstudio\AiDataQuery\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class BootstrapDataQueryEntityRegistry implements DataPatchInterface
{
    private const TOOL_CODE = 'query_entity';

    private ResourceConnection $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    public function apply()
    {
        $connection = $this->resourceConnection->getConnection();

        // Create query_entity tool if it doesn't exist
        if (!$this->toolExists($connection)) {
            $this->createQueryEntityTool($connection);
        }

        // Bootstrap common queryable entities
        $this->bootstrapEntities($connection);

        return $this;
    }

    private function createQueryEntityTool($connection): void
    {
        $toolsTable = $this->resourceConnection->getTableName('gtstudio_ai_tools');

        $properties = [
            [
                'name' => 'entity_code',
                'type' => 'string',
                'description' => 'Entity code to query (e.g., sales_order, customer_entity, catalog_product)',
                'required' => true,
            ],
            [
                'name' => 'fields',
                'type' => 'array',
                'description' => 'Specific fields to return (optional, defaults to searchable fields)',
                'required' => false,
            ],
            [
                'name' => 'filters',
                'type' => 'object',
                'description' => 'Filter conditions as field: value pairs',
                'required' => false,
            ],
            [
                'name' => 'sort',
                'type' => 'object',
                'description' => 'Sort configuration with field and direction (ASC/DESC)',
                'required' => false,
            ],
            [
                'name' => 'limit',
                'type' => 'integer',
                'description' => 'Maximum results to return (1-100)',
                'required' => false,
            ],
        ];

        $data = [
            'code' => self::TOOL_CODE,
            'description' => 'Query any registered Magento entity by code, with optional filters, sorting, and field selection',
            'properties' => json_encode($properties),
            'additional_configs' => json_encode([
                'executor' => 'Gtstudio\AiDataQuery\Model\Tool\GetEntityDataExecutor',
            ]),
        ];

        $connection->insert($toolsTable, $data);
    }

    private function bootstrapEntities($connection): void
    {
        $entitiesTable = $this->resourceConnection->getTableName('gtstudio_ai_entities');

        $entities = [
            [
                'code' => 'sales_order',
                'label' => 'Orders',
                'description' => 'Store orders',
                'collection_class' => 'Magento\Sales\Model\ResourceModel\Order\Collection',
                'searchable_fields' => json_encode(['entity_id', 'increment_id', 'customer_email', 'status', 'created_at', 'grand_total']),
                'filterable_fields' => json_encode(['status', 'created_at', 'grand_total', 'customer_email']),
                'sortable_fields' => json_encode(['created_at', 'grand_total', 'entity_id']),
                'max_results' => 100,
                'is_active' => 1,
            ],
            [
                'code' => 'customer_entity',
                'label' => 'Customers',
                'description' => 'Registered customers',
                'collection_class' => 'Magento\Customer\Model\ResourceModel\Customer\Collection',
                'searchable_fields' => json_encode(['entity_id', 'email', 'firstname', 'lastname', 'created_at']),
                'filterable_fields' => json_encode(['email', 'created_at', 'group_id']),
                'sortable_fields' => json_encode(['created_at', 'email']),
                'max_results' => 100,
                'is_active' => 1,
            ],
            [
                'code' => 'catalog_product',
                'label' => 'Products',
                'description' => 'Catalog products',
                'collection_class' => 'Magento\Catalog\Model\ResourceModel\Product\Collection',
                'searchable_fields' => json_encode(['entity_id', 'sku', 'name', 'price', 'status', 'visibility']),
                'filterable_fields' => json_encode(['status', 'visibility', 'price', 'sku']),
                'sortable_fields' => json_encode(['name', 'price', 'created_at']),
                'max_results' => 100,
                'is_active' => 1,
            ],
            [
                'code' => 'sales_invoice',
                'label' => 'Invoices',
                'description' => 'Sales invoices',
                'collection_class' => 'Magento\Sales\Model\ResourceModel\Order\Invoice\Collection',
                'searchable_fields' => json_encode(['entity_id', 'increment_id', 'order_id', 'status', 'created_at']),
                'filterable_fields' => json_encode(['status', 'created_at', 'order_id']),
                'sortable_fields' => json_encode(['created_at', 'entity_id']),
                'max_results' => 100,
                'is_active' => 1,
            ],
            [
                'code' => 'sales_shipment',
                'label' => 'Shipments',
                'description' => 'Order shipments',
                'collection_class' => 'Magento\Sales\Model\ResourceModel\Order\Shipment\Collection',
                'searchable_fields' => json_encode(['entity_id', 'increment_id', 'order_id', 'created_at']),
                'filterable_fields' => json_encode(['order_id', 'created_at']),
                'sortable_fields' => json_encode(['created_at', 'entity_id']),
                'max_results' => 100,
                'is_active' => 1,
            ],
        ];

        foreach ($entities as $entity) {
            // Check if entity code already exists
            $select = $connection->select()->from($entitiesTable)->where('code = ?', $entity['code']);
            $existing = $connection->fetchOne($select);

            if (!$existing) {
                $connection->insert($entitiesTable, $entity);
            }
        }
    }

    private function toolExists($connection): bool
    {
        $toolsTable = $this->resourceConnection->getTableName('gtstudio_ai_tools');
        $select = $connection->select()->from($toolsTable)->where('code = ?', self::TOOL_CODE);
        $existing = $connection->fetchOne($select);

        return (bool)$existing;
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
