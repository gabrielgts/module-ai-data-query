<?php

declare(strict_types=1);

namespace Gtstudio\AiDataQuery\Model\StructuredOutput;

use NeuronAI\StructuredOutput\SchemaProperty;

/**
 * Structured output DTO for the query planner LLM call.
 *
 * The LLM receives only the user's question and returns this object.
 * No store data is included in the LLM conversation at any point.
 */
class DataQueryIntent
{
    /**
     * The tool code to execute.
     * Must be one of: order_analytics, customer_lifetime_value, product_performance, query_entity
     */
    #[SchemaProperty(
        description: 'The tool code to execute. Must be exactly one of: '
            . 'order_analytics, customer_lifetime_value, product_performance, query_entity',
        required: true
    )]
    public string $tool_code = '';

    /**
     * JSON-encoded string of parameters for the chosen tool.
     * Example: {"analysis_type":"daily_sales","days":30}
     */
    #[SchemaProperty(
        description: 'JSON-encoded string of parameters for the selected tool. '
            . 'Example for order_analytics: {"analysis_type":"daily_sales","days":30}. '
            . 'Example for product_performance: {"analysis_type":"top_sellers","limit":10}.',
        required: true
    )]
    public string $parameters_json = '{}';

    /**
     * A single short sentence that will introduce the query results to the user.
     * Example: "Here are your daily sales for the last 30 days:"
     */
    #[SchemaProperty(
        description: 'A single friendly sentence introducing the results to the user. '
            . 'Example: "Here are your top 10 products by revenue this month:"',
        required: true
    )]
    public string $explanation = '';
}
