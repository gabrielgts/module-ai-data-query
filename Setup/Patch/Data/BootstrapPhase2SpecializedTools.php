<?php

declare(strict_types=1);

namespace Gtstudio\AiDataQuery\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Gtstudio\AiDataQuery\Setup\Patch\Data\BootstrapDataQueryEntityRegistry;

class BootstrapPhase2SpecializedTools implements DataPatchInterface
{
    private ResourceConnection $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    public function apply()
    {
        $connection = $this->resourceConnection->getConnection();

        // Create Phase 2 specialized tools
        $this->createOrderAnalyticsTool($connection);
        $this->createCustomerLifetimeValueTool($connection);
        $this->createProductPerformanceTool($connection);

        return $this;
    }

    private function createOrderAnalyticsTool($connection): void
    {
        if ($this->toolExists($connection, 'order_analytics')) {
            return;
        }

        $toolsTable = $this->resourceConnection->getTableName('gtstudio_ai_tools');

        $properties = [
            [
                'name' => 'analysis_type',
                'type' => 'string',
                'description' => 'Type of analysis: daily_sales, order_status, avg_order_value, top_customers, sales_by_period',
                'required' => true,
            ],
            [
                'name' => 'days',
                'type' => 'integer',
                'description' => 'Number of days to analyze (default: 30)',
                'required' => false,
            ],
            [
                'name' => 'limit',
                'type' => 'integer',
                'description' => 'Max results to return (1-100)',
                'required' => false,
            ],
            [
                'name' => 'period',
                'type' => 'string',
                'description' => 'Time period for sales_by_period: month, quarter, year',
                'required' => false,
            ],
        ];

        $data = [
            'code' => 'order_analytics',
            'description' => 'Analyze order metrics, trends, revenue, and customer spending patterns',
            'properties' => json_encode($properties),
            'additional_configs' => json_encode([
                'executor' => 'Gtstudio\AiDataQuery\Model\Tool\GetOrderAnalyticsExecutor',
            ]),
        ];

        $connection->insert($toolsTable, $data);
    }

    private function createCustomerLifetimeValueTool($connection): void
    {
        if ($this->toolExists($connection, 'customer_lifetime_value')) {
            return;
        }

        $toolsTable = $this->resourceConnection->getTableName('gtstudio_ai_tools');

        $properties = [
            [
                'name' => 'analysis_type',
                'type' => 'string',
                'description' => 'Type of analysis: lifetime_value, rfm_analysis, acquisition_trends, top_segment',
                'required' => true,
            ],
            [
                'name' => 'months',
                'type' => 'integer',
                'description' => 'Number of months to analyze (default: 12)',
                'required' => false,
            ],
            [
                'name' => 'segment',
                'type' => 'string',
                'description' => 'Customer segment for top_segment: best_customers, repeat_buyers, new_customers',
                'required' => false,
            ],
        ];

        $data = [
            'code' => 'customer_lifetime_value',
            'description' => 'Analyze customer lifetime value, RFM segmentation, acquisition trends, and customer segments',
            'properties' => json_encode($properties),
            'additional_configs' => json_encode([
                'executor' => 'Gtstudio\AiDataQuery\Model\Tool\GetCustomerLifetimeValueExecutor',
            ]),
        ];

        $connection->insert($toolsTable, $data);
    }

    private function createProductPerformanceTool($connection): void
    {
        if ($this->toolExists($connection, 'product_performance')) {
            return;
        }

        $toolsTable = $this->resourceConnection->getTableName('gtstudio_ai_tools');

        $properties = [
            [
                'name' => 'analysis_type',
                'type' => 'string',
                'description' => 'Type of analysis: top_sellers, low_performers, revenue_by_category, product_trends, inventory_alert',
                'required' => true,
            ],
            [
                'name' => 'days',
                'type' => 'integer',
                'description' => 'Number of days to analyze (default: 90)',
                'required' => false,
            ],
            [
                'name' => 'limit',
                'type' => 'integer',
                'description' => 'Max results to return (1-100)',
                'required' => false,
            ],
            [
                'name' => 'threshold',
                'type' => 'integer',
                'description' => 'Inventory threshold for inventory_alert (default: 10)',
                'required' => false,
            ],
        ];

        $data = [
            'code' => 'product_performance',
            'description' => 'Analyze product performance, sales trends, category revenue, inventory levels, and sales metrics',
            'properties' => json_encode($properties),
            'additional_configs' => json_encode([
                'executor' => 'Gtstudio\AiDataQuery\Model\Tool\GetProductPerformanceExecutor',
            ]),
        ];

        $connection->insert($toolsTable, $data);
    }

    private function toolExists($connection, string $code): bool
    {
        $toolsTable = $this->resourceConnection->getTableName('gtstudio_ai_tools');
        $select = $connection->select()->from($toolsTable)->where('code = ?', $code);
        $existing = $connection->fetchOne($select);

        return (bool)$existing;
    }

    public static function getDependencies()
    {
        return [
            BootstrapDataQueryEntityRegistry::class,
        ];
    }

    public function getAliases()
    {
        return [];
    }
}
