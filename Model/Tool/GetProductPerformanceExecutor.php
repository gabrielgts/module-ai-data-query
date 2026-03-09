<?php

namespace Gtstudio\AiDataQuery\Model\Tool;

use Gtstudio\AiAgents\Api\ToolExecutorInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class GetProductPerformanceExecutor implements ToolExecutorInterface
{
    private ResourceConnection $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    public function execute(array $inputs): mixed
    {
        $analysisType = $inputs['analysis_type'] ?? null;

        if (empty($analysisType)) {
            return "Error: analysis_type parameter is required (top_sellers, low_performers, revenue_by_category, product_trends, inventory_alert)";
        }

        try {
            return match ($analysisType) {
                'top_sellers' => $this->getTopSellers($inputs),
                'low_performers' => $this->getLowPerformers($inputs),
                'revenue_by_category' => $this->getRevenueByCategory($inputs),
                'product_trends' => $this->getProductTrends($inputs),
                'inventory_alert' => $this->getInventoryAlert($inputs),
                default => "Error: Unknown analysis_type: $analysisType",
            };
        } catch (LocalizedException $e) {
            return "Error: " . $e->getMessage();
        } catch (\Exception $e) {
            return "Error: Failed to analyze products: " . $e->getMessage();
        }
    }

    private function getTopSellers(array $inputs): string
    {
        $limit = min((int)($inputs['limit'] ?? 20), 100);
        $days = (int)($inputs['days'] ?? 90);

        $connection = $this->resourceConnection->getConnection();
        $productTable = $this->resourceConnection->getTableName('catalog_product_entity');
        $orderItemTable = $this->resourceConnection->getTableName('sales_order_item');
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $startDate = date('Y-m-d', strtotime("-$days days"));

        $sql = "
            SELECT
                p.sku,
                p.entity_id,
                SUM(oi.qty_ordered) as total_quantity,
                SUM(oi.row_total) as total_revenue,
                COUNT(DISTINCT o.entity_id) as order_count,
                AVG(oi.price) as avg_price
            FROM $productTable p
            JOIN $orderItemTable oi ON p.entity_id = oi.product_id
            JOIN $orderTable o ON oi.order_id = o.entity_id
            WHERE o.created_at >= '$startDate'
            GROUP BY p.entity_id, p.sku
            ORDER BY total_revenue DESC
            LIMIT $limit
        ";

        $results = $connection->fetchAll($sql);

        if (empty($results)) {
            return "No sales data found in the last $days days.";
        }

        $summary = "Top $limit Selling Products (Last $days days):\n\n";
        $totalRevenue = 0;
        $totalQuantity = 0;

        foreach ($results as $index => $product) {
            $summary .= sprintf(
                "%d. SKU %s: %d units sold, Revenue: $%.2f (Avg: $%.2f per unit)\n",
                $index + 1,
                $product['sku'],
                $product['total_quantity'],
                $product['total_revenue'],
                $product['avg_price']
            );
            $totalRevenue += $product['total_revenue'];
            $totalQuantity += $product['total_quantity'];
        }

        $summary .= sprintf(
            "\nTotal: %d units, $%.2f revenue\n",
            $totalQuantity,
            $totalRevenue
        );

        return $summary;
    }

    private function getLowPerformers(array $inputs): string
    {
        $limit = min((int)($inputs['limit'] ?? 20), 100);
        $days = (int)($inputs['days'] ?? 90);

        $connection = $this->resourceConnection->getConnection();
        $productTable = $this->resourceConnection->getTableName('catalog_product_entity');
        $orderItemTable = $this->resourceConnection->getTableName('sales_order_item');
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $startDate = date('Y-m-d', strtotime("-$days days"));

        // Find products with inventory but low/no recent sales
        $sql = "
            SELECT
                p.sku,
                p.entity_id,
                COALESCE(SUM(oi.qty_ordered), 0) as total_quantity,
                COALESCE(SUM(oi.row_total), 0) as total_revenue,
                COUNT(DISTINCT o.entity_id) as order_count
            FROM $productTable p
            LEFT JOIN $orderItemTable oi ON p.entity_id = oi.product_id
            LEFT JOIN $orderTable o ON oi.order_id = o.entity_id AND o.created_at >= '$startDate'
            WHERE p.entity_type_id = 4
            GROUP BY p.entity_id, p.sku
            HAVING total_quantity = 0
            ORDER BY p.entity_id DESC
            LIMIT $limit
        ";

        $results = $connection->fetchAll($sql);

        if (empty($results)) {
            return "All products are performing well (no products with zero sales in last $days days).";
        }

        $summary = "Low Performers ($limit products with zero sales in last $days days):\n\n";

        foreach ($results as $index => $product) {
            $summary .= sprintf(
                "%d. SKU %s (ID: %d) - No sales in last $days days\n",
                $index + 1,
                $product['sku'],
                $product['entity_id']
            );
        }

        return $summary;
    }

    private function getRevenueByCategory(array $inputs): string
    {
        $days = (int)($inputs['days'] ?? 30);

        $connection = $this->resourceConnection->getConnection();
        $categoryTable = $this->resourceConnection->getTableName('catalog_category_entity');
        $productCategoryTable = $this->resourceConnection->getTableName('catalog_category_product');
        $productTable = $this->resourceConnection->getTableName('catalog_product_entity');
        $orderItemTable = $this->resourceConnection->getTableName('sales_order_item');
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $startDate = date('Y-m-d', strtotime("-$days days"));

        $sql = "
            SELECT
                c.entity_id,
                c.name as category_name,
                COUNT(DISTINCT p.entity_id) as product_count,
                SUM(oi.qty_ordered) as units_sold,
                SUM(oi.row_total) as category_revenue
            FROM $categoryTable c
            JOIN $productCategoryTable pc ON c.entity_id = pc.category_id
            JOIN $productTable p ON pc.product_id = p.entity_id
            LEFT JOIN $orderItemTable oi ON p.entity_id = oi.product_id
            LEFT JOIN $orderTable o ON oi.order_id = o.entity_id AND o.created_at >= '$startDate'
            WHERE c.entity_id > 1
            GROUP BY c.entity_id, c.name
            ORDER BY category_revenue DESC
            LIMIT 20
        ";

        $results = $connection->fetchAll($sql);

        if (empty($results)) {
            return "No category data found in the last $days days.";
        }

        $summary = "Revenue by Category (Last $days days):\n\n";
        $totalRevenue = 0;

        foreach ($results as $index => $category) {
            $summary .= sprintf(
                "%d. %s: $%.2f (%d units from %d products)\n",
                $index + 1,
                $category['category_name'],
                $category['category_revenue'] ?? 0,
                $category['units_sold'] ?? 0,
                $category['product_count']
            );
            $totalRevenue += $category['category_revenue'] ?? 0;
        }

        $summary .= sprintf("\nTotal Category Revenue: $%.2f\n", $totalRevenue);

        return $summary;
    }

    private function getProductTrends(array $inputs): string
    {
        $days = (int)($inputs['days'] ?? 30);

        $connection = $this->resourceConnection->getConnection();
        $productTable = $this->resourceConnection->getTableName('catalog_product_entity');
        $orderItemTable = $this->resourceConnection->getTableName('sales_order_item');
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $startDate = date('Y-m-d', strtotime("-$days days"));

        $sql = "
            SELECT
                DATE_FORMAT(o.created_at, '%Y-%m-%d') as sale_date,
                COUNT(DISTINCT p.entity_id) as unique_products,
                SUM(oi.qty_ordered) as total_units,
                SUM(oi.row_total) as daily_revenue
            FROM $productTable p
            JOIN $orderItemTable oi ON p.entity_id = oi.product_id
            JOIN $orderTable o ON oi.order_id = o.entity_id
            WHERE o.created_at >= '$startDate'
            GROUP BY DATE_FORMAT(o.created_at, '%Y-%m-%d')
            ORDER BY sale_date DESC
            LIMIT 30
        ";

        $results = $connection->fetchAll($sql);

        if (empty($results)) {
            return "No product sales trend data found in the last $days days.";
        }

        $summary = "Product Sales Trends (Last $days days):\n\n";

        foreach ($results as $row) {
            $summary .= sprintf(
                "%s: %d units sold, $%.2f revenue (%d unique products)\n",
                $row['sale_date'],
                $row['total_units'],
                $row['daily_revenue'],
                $row['unique_products']
            );
        }

        return $summary;
    }

    private function getInventoryAlert(array $inputs): string
    {
        $threshold = (int)($inputs['threshold'] ?? 10);

        $connection = $this->resourceConnection->getConnection();
        $productTable = $this->resourceConnection->getTableName('catalog_product_entity');
        $stockTable = $this->resourceConnection->getTableName('cataloginventory_stock_item');

        $sql = "
            SELECT
                p.sku,
                p.entity_id,
                s.qty as quantity_in_stock,
                s.is_in_stock
            FROM $productTable p
            LEFT JOIN $stockTable s ON p.entity_id = s.product_id
            WHERE s.qty <= $threshold OR s.is_in_stock = 0
            ORDER BY s.qty ASC
            LIMIT 50
        ";

        $results = $connection->fetchAll($sql);

        if (empty($results)) {
            return "All products have healthy inventory levels (above $threshold units).";
        }

        $summary = "Low Inventory Alert (Products below $threshold units):\n\n";
        $outOfStock = 0;
        $lowStock = 0;

        foreach ($results as $product) {
            if ($product['is_in_stock'] == 0) {
                $summary .= sprintf("OUT OF STOCK: SKU %s (%d units)\n", $product['sku'], $product['quantity_in_stock']);
                $outOfStock++;
            } else {
                $summary .= sprintf("LOW STOCK: SKU %s (%d units)\n", $product['sku'], $product['quantity_in_stock']);
                $lowStock++;
            }
        }

        $summary .= sprintf(
            "\nSummary: %d out of stock, %d low stock\n",
            $outOfStock,
            $lowStock
        );

        return $summary;
    }
}
