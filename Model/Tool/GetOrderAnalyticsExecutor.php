<?php

namespace Gtstudio\AiDataQuery\Model\Tool;

use Gtstudio\AiAgents\Api\ToolExecutorInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;

class GetOrderAnalyticsExecutor implements ToolExecutorInterface
{
    /** @var ResourceConnection */
    private ResourceConnection $resourceConnection;

    /** @var CollectionFactory */
    private CollectionFactory $orderCollectionFactory;

    /**
     * @param ResourceConnection $resourceConnection
     * @param CollectionFactory $orderCollectionFactory
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        CollectionFactory $orderCollectionFactory
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * Execute the order analytics analysis.
     *
     * @param array $inputs
     * @return mixed
     */
    public function execute(array $inputs): mixed
    {
        $analysisType = $inputs['analysis_type'] ?? null;

        if (empty($analysisType)) {
            return "Error: analysis_type parameter is required "
                . "(daily_sales, order_status, avg_order_value, top_customers, sales_by_period)";
        }

        try {
            return match ($analysisType) {
                'daily_sales' => $this->getDailySalesAnalysis($inputs),
                'order_status' => $this->getOrderStatusAnalysis($inputs),
                'avg_order_value' => $this->getAverageOrderValue($inputs),
                'top_customers' => $this->getTopCustomers($inputs),
                'sales_by_period' => $this->getSalesByPeriod($inputs),
                default => "Error: Unknown analysis_type: $analysisType",
            };
        } catch (LocalizedException $e) {
            return "Error: " . $e->getMessage();
        } catch (\Exception $e) {
            return "Error: Failed to analyze orders: " . $e->getMessage();
        }
    }

    /**
     * Get daily sales analysis.
     *
     * @param array $inputs
     * @return string
     */
    private function getDailySalesAnalysis(array $inputs): string
    {
        $connection = $this->resourceConnection->getConnection();
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');

        $days = (int)($inputs['days'] ?? 30);
        $startDate = date('Y-m-d', strtotime("-$days days"));

        $sql = "
            SELECT
                DATE(created_at) as order_date,
                COUNT(*) as order_count,
                SUM(grand_total) as daily_revenue
            FROM $salesOrderTable
            WHERE created_at >= '$startDate'
            GROUP BY DATE(created_at)
            ORDER BY order_date DESC
        ";

        $results = $connection->fetchAll($sql);

        if (empty($results)) {
            return "No orders found in the last $days days.";
        }

        $summary = "Daily Sales Analytics (Last $days days):\n\n";
        $totalRevenue = 0;
        $totalOrders = 0;

        foreach ($results as $row) {
            $summary .= sprintf(
                "%s: %d orders, Revenue: $%.2f\n",
                $row['order_date'],
                $row['order_count'],
                $row['daily_revenue']
            );
            $totalRevenue += $row['daily_revenue'];
            $totalOrders += $row['order_count'];
        }

        $avgDaily = count($results) > 0 ? $totalRevenue / count($results) : 0;
        $summary .= sprintf(
            "\nSummary:\nTotal Orders: %d\nTotal Revenue: $%.2f\nAverage Daily Revenue: $%.2f\n",
            $totalOrders,
            $totalRevenue,
            $avgDaily
        );

        return $summary;
    }

    /**
     * Get order count and revenue broken down by status.
     *
     * @param array $inputs
     * @return string
     */
    private function getOrderStatusAnalysis(array $inputs): string
    {
        $connection = $this->resourceConnection->getConnection();
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');

        $sql = "
            SELECT
                status,
                COUNT(*) as count,
                SUM(grand_total) as revenue
            FROM $salesOrderTable
            GROUP BY status
            ORDER BY count DESC
        ";

        $results = $connection->fetchAll($sql);

        if (empty($results)) {
            return "No orders found.";
        }

        $summary = "Order Status Analysis:\n\n";
        $totalOrders = 0;
        $totalRevenue = 0;

        foreach ($results as $row) {
            $summary .= sprintf(
                "%s: %d orders ($%.2f)\n",
                ucfirst($row['status']),
                $row['count'],
                $row['revenue']
            );
            $totalOrders += $row['count'];
            $totalRevenue += $row['revenue'];
        }

        $summary .= sprintf("\nTotal: %d orders, $%.2f revenue\n", $totalOrders, $totalRevenue);

        return $summary;
    }

    /**
     * Get average, min, and max order values.
     *
     * @param array $inputs
     * @return string
     */
    private function getAverageOrderValue(array $inputs): string
    {
        $connection = $this->resourceConnection->getConnection();
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');

        $days = (int)($inputs['days'] ?? 365);
        $startDate = date('Y-m-d', strtotime("-$days days"));

        $sql = "
            SELECT
                AVG(grand_total) as avg_order_value,
                MIN(grand_total) as min_order,
                MAX(grand_total) as max_order,
                COUNT(*) as total_orders,
                SUM(grand_total) as total_revenue
            FROM $salesOrderTable
            WHERE created_at >= '$startDate'
        ";

        $result = $connection->fetchRow($sql);

        if (!$result || $result['total_orders'] == 0) {
            return "No orders found in the last $days days.";
        }

        return sprintf(
            "Average Order Value Analysis (Last %d days):\n" .
            "Average Order Value: $%.2f\n" .
            "Minimum Order: $%.2f\n" .
            "Maximum Order: $%.2f\n" .
            "Total Orders: %d\n" .
            "Total Revenue: $%.2f\n",
            $days,
            $result['avg_order_value'],
            $result['min_order'],
            $result['max_order'],
            $result['total_orders'],
            $result['total_revenue']
        );
    }

    /**
     * Get top customers ranked by total spend.
     *
     * @param array $inputs
     * @return string
     */
    private function getTopCustomers(array $inputs): string
    {
        $connection = $this->resourceConnection->getConnection();
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');

        $limit = min((int)($inputs['limit'] ?? 10), 100);

        $sql = "
            SELECT
                customer_email,
                COUNT(*) as order_count,
                SUM(grand_total) as total_spent
            FROM $salesOrderTable
            WHERE customer_email IS NOT NULL
            GROUP BY customer_email
            ORDER BY total_spent DESC
            LIMIT $limit
        ";

        $results = $connection->fetchAll($sql);

        if (empty($results)) {
            return "No orders found.";
        }

        $summary = "Top $limit Customers by Spend:\n\n";
        foreach ($results as $index => $customer) {
            $summary .= sprintf(
                "%d. %s: %d orders ($%.2f)\n",
                $index + 1,
                $customer['customer_email'],
                $customer['order_count'],
                $customer['total_spent']
            );
        }

        return $summary;
    }

    /**
     * Get sales aggregated by period (month, quarter, or year).
     *
     * @param array $inputs
     * @return string
     */
    private function getSalesByPeriod(array $inputs): string
    {
        $period = $inputs['period'] ?? 'month'; // month, quarter, year
        $years = (int)($inputs['years'] ?? 1);

        $connection = $this->resourceConnection->getConnection();
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');

        $dateFormat = match ($period) {
            'quarter' => 'CONCAT(YEAR(created_at), "-Q", QUARTER(created_at))',
            'year' => 'YEAR(created_at)',
            default => 'DATE_FORMAT(created_at, "%Y-%m")',
        };

        $startDate = date('Y-m-d', strtotime("-$years years"));

        $sql = "
            SELECT
                $dateFormat as period,
                COUNT(*) as order_count,
                SUM(grand_total) as revenue
            FROM $salesOrderTable
            WHERE created_at >= '$startDate'
            GROUP BY $dateFormat
            ORDER BY period DESC
        ";

        $results = $connection->fetchAll($sql);

        if (empty($results)) {
            return "No orders found in the last $years year(s).";
        }

        $summary = "Sales by " . ucfirst($period) . " (Last $years year(s)):\n\n";
        foreach ($results as $row) {
            $summary .= sprintf(
                "%s: %d orders, Revenue: $%.2f\n",
                $row['period'],
                $row['order_count'],
                $row['revenue']
            );
        }

        return $summary;
    }
}
