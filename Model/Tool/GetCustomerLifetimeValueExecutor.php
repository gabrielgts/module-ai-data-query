<?php

namespace Gtstudio\AiDataQuery\Model\Tool;

use Gtstudio\AiAgents\Api\ToolExecutorInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class GetCustomerLifetimeValueExecutor implements ToolExecutorInterface
{
    /** @var ResourceConnection */
    private ResourceConnection $resourceConnection;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Execute the customer lifetime value analysis.
     *
     * @param array $inputs
     * @return mixed
     */
    public function execute(array $inputs): mixed
    {
        $analysisType = $inputs['analysis_type'] ?? null;

        if (empty($analysisType)) {
            return "Error: analysis_type parameter is required "
                . "(lifetime_value, rfm_analysis, acquisition_trends, top_segment)";
        }

        try {
            return match ($analysisType) {
                'lifetime_value' => $this->getLifetimeValueDistribution($inputs),
                'rfm_analysis' => $this->getRFMAnalysis($inputs),
                'acquisition_trends' => $this->getAcquisitionTrends($inputs),
                'top_segment' => $this->getTopSegment($inputs),
                default => "Error: Unknown analysis_type: $analysisType",
            };
        } catch (LocalizedException $e) {
            return "Error: " . $e->getMessage();
        } catch (\Exception $e) {
            return "Error: Failed to analyze customers: " . $e->getMessage();
        }
    }

    /**
     * Get lifetime value distribution across customer tiers.
     *
     * @param array $inputs
     * @return string
     */
    private function getLifetimeValueDistribution(array $inputs): string
    {
        $connection = $this->resourceConnection->getConnection();
        $customerTable = $this->resourceConnection->getTableName('customer_entity');
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');

        $sql = "
            SELECT
                c.email,
                COUNT(o.entity_id) as order_count,
                SUM(o.grand_total) as lifetime_value,
                MAX(o.created_at) as last_order_date,
                MIN(o.created_at) as first_order_date
            FROM $customerTable c
            LEFT JOIN $salesOrderTable o ON c.entity_id = o.customer_id
            WHERE c.email IS NOT NULL
            GROUP BY c.entity_id, c.email
            HAVING order_count > 0
            ORDER BY lifetime_value DESC
            LIMIT 100
        ";

        $results = $connection->fetchAll($sql);

        if (empty($results)) {
            return "No customer data found.";
        }

        $summary = "Customer Lifetime Value Distribution:\n\n";
        $avgLTV = 0;
        $totalCustomers = count($results);
        $totalLTV = 0;

        // Segment into tiers
        $high_value = array_filter($results, fn($r) => $r['lifetime_value'] > 1000);
        $mid_value = array_filter($results, fn($r) => $r['lifetime_value'] > 100 && $r['lifetime_value'] <= 1000);
        $low_value = array_filter($results, fn($r) => $r['lifetime_value'] <= 100);

        foreach ($results as $customer) {
            $totalLTV += $customer['lifetime_value'];
        }
        $avgLTV = $totalCustomers > 0 ? $totalLTV / $totalCustomers : 0;

        $summary .= sprintf(
            "High-Value Customers (>$1000): %d customers\n" .
            "Mid-Value Customers ($100-$999): %d customers\n" .
            "Low-Value Customers (<$100): %d customers\n\n" .
            "Average LTV: $%.2f\n" .
            "Total Customers: %d\n" .
            "Total Revenue: $%.2f\n\n" .
            "Top 5 Customers:\n",
            count($high_value),
            count($mid_value),
            count($low_value),
            $avgLTV,
            $totalCustomers,
            $totalLTV
        );

        $top5 = array_slice($results, 0, 5);
        foreach ($top5 as $index => $customer) {
            $summary .= sprintf(
                "%d. %s: $%.2f (Orders: %d)\n",
                $index + 1,
                $customer['email'],
                $customer['lifetime_value'],
                $customer['order_count']
            );
        }

        return $summary;
    }

    /**
     * Get RFM (Recency, Frequency, Monetary) analysis.
     *
     * @param array $inputs
     * @return string
     */
    private function getRFMAnalysis(array $inputs): string
    {
        $connection = $this->resourceConnection->getConnection();
        $customerTable = $this->resourceConnection->getTableName('customer_entity');
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');

        // RFM: Recency (days since last purchase), Frequency, Monetary (total spend)
        $sql = "
            SELECT
                c.email,
                DATEDIFF(NOW(), MAX(o.created_at)) as recency_days,
                COUNT(o.entity_id) as frequency,
                SUM(o.grand_total) as monetary_value
            FROM $customerTable c
            LEFT JOIN $salesOrderTable o ON c.entity_id = o.customer_id
            WHERE c.email IS NOT NULL
            GROUP BY c.entity_id, c.email
            HAVING frequency > 0
            ORDER BY recency_days ASC
            LIMIT 100
        ";

        $results = $connection->fetchAll($sql);

        if (empty($results)) {
            return "No customer data found.";
        }

        $summary = "RFM Analysis (Recency, Frequency, Monetary):\n\n";

        // Segment by engagement
        $active = array_filter($results, fn($r) => $r['recency_days'] <= 30);
        $at_risk = array_filter($results, fn($r) => $r['recency_days'] > 30 && $r['recency_days'] <= 90);
        $dormant = array_filter($results, fn($r) => $r['recency_days'] > 90);

        $summary .= sprintf(
            "Active Customers (purchased in last 30 days): %d\n" .
            "At-Risk Customers (30-90 days): %d\n" .
            "Dormant Customers (>90 days): %d\n\n" .
            "Recent High-Value Customers:\n",
            count($active),
            count($at_risk),
            count($dormant)
        );

        $recent_high_value = array_slice(
            array_filter($results, fn($r) => $r['recency_days'] <= 30),
            0,
            5
        );

        foreach ($recent_high_value as $index => $customer) {
            $summary .= sprintf(
                "%d. %s: $%.2f spent, %d orders, last purchase %d days ago\n",
                $index + 1,
                $customer['email'],
                $customer['monetary_value'],
                $customer['frequency'],
                $customer['recency_days']
            );
        }

        return $summary;
    }

    /**
     * Get customer acquisition trends by month.
     *
     * @param array $inputs
     * @return string
     */
    private function getAcquisitionTrends(array $inputs): string
    {
        $months = (int)($inputs['months'] ?? 12);

        $connection = $this->resourceConnection->getConnection();
        $customerTable = $this->resourceConnection->getTableName('customer_entity');

        $startDate = date('Y-m-d', strtotime("-$months months"));

        $sql = "
            SELECT
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as new_customers,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_customers
            FROM $customerTable
            WHERE created_at >= '$startDate'
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
        ";

        $results = $connection->fetchAll($sql);

        if (empty($results)) {
            return "No customer acquisition data found in the last $months months.";
        }

        $summary = "Customer Acquisition Trends (Last $months months):\n\n";
        $totalNew = 0;

        foreach ($results as $row) {
            $summary .= sprintf(
                "%s: %d new customers (%d active)\n",
                $row['month'],
                $row['new_customers'],
                $row['active_customers']
            );
            $totalNew += $row['new_customers'];
        }

        $avgPerMonth = count($results) > 0 ? $totalNew / count($results) : 0;
        $summary .= sprintf("\nTotal New Customers: %d\nAverage per Month: %.0f\n", $totalNew, $avgPerMonth);

        return $summary;
    }

    /**
     * Get top customers for a given segment.
     *
     * @param array $inputs
     * @return string
     */
    private function getTopSegment(array $inputs): string
    {
        $segment = $inputs['segment'] ?? 'best_customers'; // best_customers, repeat_buyers, new_customers

        $connection = $this->resourceConnection->getConnection();
        $customerTable = $this->resourceConnection->getTableName('customer_entity');
        $salesOrderTable = $this->resourceConnection->getTableName('sales_order');

        switch ($segment) {
            case 'best_customers':
                $sql = "
                    SELECT
                        c.email,
                        COUNT(o.entity_id) as orders,
                        SUM(o.grand_total) as total_spent
                    FROM $customerTable c
                    LEFT JOIN $salesOrderTable o ON c.entity_id = o.customer_id
                    WHERE c.email IS NOT NULL
                    GROUP BY c.entity_id, c.email
                    ORDER BY total_spent DESC
                    LIMIT 20
                ";
                $title = "Top 20 Customers by Total Spend";
                break;

            case 'repeat_buyers':
                $sql = "
                    SELECT
                        c.email,
                        COUNT(o.entity_id) as order_count,
                        SUM(o.grand_total) as total_spent
                    FROM $customerTable c
                    LEFT JOIN $salesOrderTable o ON c.entity_id = o.customer_id
                    WHERE c.email IS NOT NULL
                    GROUP BY c.entity_id, c.email
                    HAVING order_count >= 3
                    ORDER BY order_count DESC
                    LIMIT 20
                ";
                $title = "Top 20 Repeat Buyers (3+ orders)";
                break;

            case 'new_customers':
                $sql = "
                    SELECT
                        c.email,
                        COUNT(o.entity_id) as orders,
                        SUM(o.grand_total) as total_spent,
                        c.created_at as signup_date
                    FROM $customerTable c
                    LEFT JOIN $salesOrderTable o ON c.entity_id = o.customer_id
                    WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    AND c.email IS NOT NULL
                    GROUP BY c.entity_id, c.email
                    ORDER BY c.created_at DESC
                    LIMIT 20
                ";
                $title = "Top 20 New Customers (Last 30 Days)";
                break;

            default:
                return "Error: Unknown segment: $segment";
        }

        $results = $connection->fetchAll($sql);

        if (empty($results)) {
            return "No customers found for segment: $segment";
        }

        $summary = "$title:\n\n";
        foreach ($results as $index => $customer) {
            $summary .= sprintf(
                "%d. %s: $%.2f total",
                $index + 1,
                $customer['email'],
                $customer['total_spent']
            );

            if (isset($customer['order_count'])) {
                $summary .= sprintf(" (%d orders)", $customer['order_count']);
            }

            $summary .= "\n";
        }

        return $summary;
    }
}
