# Gtstudio_AiDataQuery

Natural-language data query interface for Magento 2. Ask questions in plain English — the AI figures out what to query, the query runs locally, and results are returned directly to the browser. **No store data is ever sent to the LLM.**

## Preview

![AiDataQuery — asking plain-English questions and getting store analytics results](docs/images/aidataquery-preview.gif)

## AI Studio Ecosystem

Part of the **AI Studio** suite for Magento 2. See all modules:

| Module | Repository | Description |
|--------|-----------|-------------|
| **Gtstudio_AiConnector** | [module-aiconnector](https://github.com/gabrielgts/module-aiconnector) | Core AI provider abstraction |
| **Gtstudio_AiAgents** | [module-ai-agents](https://github.com/gabrielgts/module-ai-agents) | Agent & tool orchestration, cron scheduling, execution log |
| **Gtstudio_AiWidgets** | [module-ai-widgets](https://github.com/gabrielgts/module-ai-widgets) | Floating admin chat widget + PageBuilder AI generator |
| **Gtstudio_AiDataQuery** | *(this module)* | Natural-language store analytics (privacy-first) |
| **Gtstudio_AiKnowledgeBase** | [module-ai-knowledge-base](https://github.com/gabrielgts/module-ai-knowledge-base) | Document upload & RAG retrieval for agents |
| **Gtstudio_AiDashboard** | [module-ai-dashboard](https://github.com/gabrielgts/module-ai-dashboard) | AI-powered KPI dashboard with ML insights |

## What It Does

- Admin chat page where users type questions like *"Show me the top 10 orders from last month"* or *"Which products have less than 5 units in stock?"*
- Two-phase privacy-first architecture:
  - **Phase 1** — LLM receives only the user's question and returns a structured intent (which tool to call, which parameters)
  - **Phase 2** — the server executes the query locally using the resolved intent and returns results directly to the browser
- Built-in query tools: order analytics, customer lifetime value, product performance, and a general entity query
- Token cost display in the chat interface

## Privacy Architecture

The LLM sees **nothing** about your store data. It only:
- Receives the user's natural-language question
- Returns a JSON object describing which analytical tool to call and with what parameters

All actual database queries run on your server. Results are displayed to the user without routing back through the AI provider.

## Requirements

- Magento 2.4.4+
- PHP 8.1+
- `Gtstudio_AiConnector` enabled and configured
- `Gtstudio_AiAgents` enabled

## Installation

```bash
composer require gtstudio/module-ai-data-query
php bin/magento module:enable Gtstudio_AiDataQuery
php bin/magento setup:upgrade
```

A database agent record with code `data_query` is created automatically via a data patch.

## Usage

Navigate to *AI Studio → Data Query*.

**Example questions:**

- *"Show total sales for the last 30 days"*
- *"What are my top 5 customers by revenue?"*
- *"List orders with status Pending from this week"*
- *"Show products with inventory below 10 units"*
- *"What is the average order value for this month?"*

## Built-in Query Tools

| Tool Code | Description |
|-----------|-------------|
| `order_analytics` | Sales totals, order status distribution, average order value, top customers |
| `customer_lifetime_value` | LTV ranking, RFM analysis, acquisition trends |
| `product_performance` | Top sellers, low performers, revenue by category, inventory alerts |
| `query_entity` | General-purpose query against any Magento entity |

## Extensibility

### Registering a Custom Query Tool

1. Implement `Gtstudio\AiAgents\Api\ToolExecutorInterface`:

```php
namespace Vendor\Module\Model\Tool;

use Gtstudio\AiAgents\Api\ToolExecutorInterface;

class StoreRevenueExecutor implements ToolExecutorInterface
{
    public function execute(array $parameters): string|array
    {
        $storeId = (int) ($parameters['store_id'] ?? 0);
        // ... query DB, return formatted results
        return "Revenue for store #{$storeId}: $12,500.00";
    }
}
```

2. Create a tool record in *AI Studio → Tools* with matching code (e.g. `store_revenue`).

3. Register the executor via `di.xml`:

```xml
<type name="Gtstudio\AiAgents\Model\Tool\ToolExecutorPool">
    <arguments>
        <argument name="executors" xsi:type="array">
            <item name="store_revenue" xsi:type="object">
                Vendor\Module\Model\Tool\StoreRevenueExecutor
            </item>
        </argument>
    </arguments>
</type>
```

4. Update the `data_query` agent's Background or Steps in the admin to describe your new tool so the LLM knows when to choose it.

### Registering a Queryable Entity

The `query_entity` tool dispatches to registered entity handlers. Add a new entity:

```xml
<!-- etc/di.xml -->
<type name="Gtstudio\AiDataQuery\Model\Tool\QueryEntityRegistry">
    <arguments>
        <argument name="entities" xsi:type="array">
            <item name="my_entity" xsi:type="object">
                Vendor\Module\Model\Tool\MyEntityHandler
            </item>
        </argument>
    </arguments>
</type>
```

Implement the handler to return a collection, field list, and filter map.

### Customising the Planner Prompt

The system prompt that instructs the LLM is built in `DataQueryChatService::buildPlannerPrompt()`. Override the service:

```xml
<preference for="Gtstudio\AiDataQuery\Model\Service\DataQueryChatService"
            type="Vendor\Module\Model\Service\CustomDataQueryChatService"/>
```

## ACL Resources

| Resource | Controls |
|----------|---------|
| `Gtstudio_AiDataQuery::management` | Access to the Data Query page and its API endpoints |
