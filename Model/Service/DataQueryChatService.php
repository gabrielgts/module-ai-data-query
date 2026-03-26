<?php

declare(strict_types=1);

namespace Gtstudio\AiDataQuery\Model\Service;

use Gtstudio\AiAgents\Model\Agent\MagentoAgentFactory;
use Gtstudio\AiAgents\Model\Tool\ToolExecutorPool;
use Gtstudio\AiConnector\Model\Client\NeuronClient;
use Gtstudio\AiConnector\Model\Config\ConfigProvider;
use Gtstudio\AiDataQuery\Model\StructuredOutput\DataQueryIntent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\StructuredOutput\JsonExtractor;
use NeuronAI\SystemPrompt;
use Psr\Log\LoggerInterface;

/**
 * Privacy-first data query service.
 *
 * Architecture (two phases):
 *
 *   Phase 1 — LLM intent extraction:
 *     The LLM receives ONLY the user's natural language question.
 *     It returns a JSON payload describing which tool to call and with what parameters.
 *     No store data is included in this conversation — not in the prompt,
 *     not in any history, and not in the response.
 *     Token usage is captured from the response for cost tracking.
 *
 *   Phase 2 — Local tool execution:
 *     The tool executor runs the DB query entirely on the server.
 *     The result is returned directly to the frontend.
 *     It is NEVER sent back to the LLM.
 *
 * Conversation history is intentionally excluded from the LLM call. Each query
 * is an independent intent-extraction step. This prevents any previously returned
 * data (which the frontend stores for display) from leaking into future LLM calls.
 */
class DataQueryChatService
{
    /**
     * @param MagentoAgentFactory $agentFactory
     * @param NeuronClient $neuronClient
     * @param ConfigProvider $configProvider
     * @param ToolExecutorPool $toolExecutorPool
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly MagentoAgentFactory $agentFactory,
        private readonly NeuronClient $neuronClient,
        private readonly ConfigProvider $configProvider,
        private readonly ToolExecutorPool $toolExecutorPool,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Resolve query intent from the user's question, then execute the tool locally.
     *
     * Store data never reaches the LLM.
     *
     * @param string $message
     * @return array
     */
    public function ask(string $message): array
    {
        ['intent' => $intent, 'tokens' => $tokens] = $this->resolveIntent($message);

        if ($intent === null) {
            return [
                'content'  => 'I could not understand your request. Please try rephrasing your question.',
                'data'     => null,
                'tool'     => null,
                'tokens'   => $tokens,
                'model'    => $this->configProvider->getModel(),
                'provider' => $this->configProvider->getProvider(),
            ];
        }

        $data = $this->executeLocally($intent);

        return [
            'content'  => $intent->explanation,
            'data'     => $data,
            'tool'     => $intent->tool_code,
            'tokens'   => $tokens,
            'model'    => $this->configProvider->getModel(),
            'provider' => $this->configProvider->getProvider(),
        ];
    }

    /**
     * Phase 1: Ask the LLM which tool to call and with what parameters.
     *
     * Uses chat() instead of structured() so that token usage is accessible
     * on the returned Message. The system prompt instructs the model to respond
     * with a JSON object matching DataQueryIntent.
     *
     * @param string $message
     * @return array{intent: DataQueryIntent|null, tokens: int}
     */
    private function resolveIntent(string $message): array
    {
        try {
            $agent = $this->agentFactory->create();
            $agent->setAiProvider($this->neuronClient->resolveProvider());
            $agent->setInstructions($this->buildPlannerPrompt());

            $response = $agent->chat(new UserMessage($message));

            $usage  = $response->getUsage();
            $tokens = ($usage?->inputTokens ?? 0) + ($usage?->outputTokens ?? 0);

            $json = (new JsonExtractor())->getJson((string) $response->getContent());

            if ($json === null) {
                $this->logger->warning('DataQueryChatService: LLM did not return valid JSON.', [
                    'response' => (string) $response->getContent(),
                ]);
                return ['intent' => null, 'tokens' => $tokens];
            }

            $data = json_decode($json, true);
            if (!is_array($data)) {
                return ['intent' => null, 'tokens' => $tokens];
            }

            $intent = new DataQueryIntent();
            $intent->tool_code       = (string) ($data['tool_code']       ?? '');
            $intent->parameters_json = (string) ($data['parameters_json'] ?? '{}');
            $intent->explanation     = (string) ($data['explanation']     ?? '');

            return ['intent' => $intent, 'tokens' => $tokens];
        } catch (\Throwable $e) {
            $this->logger->error('DataQueryChatService: failed to resolve intent.', [
                'error' => $e->getMessage(),
            ]);

            return ['intent' => null, 'tokens' => 0];
        }
    }

    /**
     * Phase 2: Execute the chosen tool on the server.
     *
     * The result is a formatted string ready for display to the user.
     * It is never sent to the LLM.
     *
     * @param DataQueryIntent $intent
     * @return string|null
     */
    private function executeLocally(DataQueryIntent $intent): ?string
    {
        $executor = $this->toolExecutorPool->get($intent->tool_code);

        if ($executor === null) {
            $this->logger->warning('DataQueryChatService: no executor registered for tool.', [
                'tool_code' => $intent->tool_code,
            ]);

            return 'Unknown tool: ' . $intent->tool_code . '. Please try a different question.';
        }

        $parameters = [];
        if (!empty($intent->parameters_json)) {
            $decoded    = json_decode($intent->parameters_json, true);
            $parameters = is_array($decoded) ? $decoded : [];
        }

        try {
            $result = $executor->execute($parameters);

            return is_string($result) ? $result : json_encode($result, JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            $this->logger->error('DataQueryChatService: tool execution failed.', [
                'tool_code' => $intent->tool_code,
                'error'     => $e->getMessage(),
            ]);

            return 'Query failed: ' . $e->getMessage();
        }
    }

    /**
     * System prompt for the query planner agent.
     *
     * Instructs the LLM to respond with a JSON object matching DataQueryIntent.
     * The model receives no store data — only the user's question and tool descriptions.
     *
     * @return string
     */
    private function buildPlannerPrompt(): string
    {
        return (string) new SystemPrompt(
            background: [
                'You are a Magento store data query planner.',
                'Your sole role is to understand the user\'s question and select the correct analytics tool'
                    . ' and parameters.',
                'You have NO access to any store data. You will never see, receive, or analyze actual store data.',
                'Store data is queried locally on the server and shown directly to the user'
                    . ' — it never passes through you.',
                'Available tools:',
                '  - order_analytics: Analyze orders. analysis_type values: daily_sales, order_status, avg_order_value,'
                    . ' top_customers, sales_by_period. Optional: days (integer), limit (integer),'
                    . ' period (month/quarter/year).',
                '  - customer_lifetime_value: Analyze customers. analysis_type values: lifetime_value, rfm_analysis,'
                    . ' acquisition_trends, top_segment. Optional: months (integer),'
                    . ' segment (best_customers/repeat_buyers/new_customers).',
                '  - product_performance: Analyze products. analysis_type values: top_sellers, low_performers,'
                    . ' revenue_by_category, product_trends, inventory_alert.'
                    . ' Optional: days (integer), limit (integer), threshold (integer).',
                '  - query_entity: General entity query.'
                    . ' Required: entity_code (sales_order/customer_entity/catalog_product'
                    . '/sales_invoice/sales_shipment).'
                    . ' Optional: fields (array), filters (object), sort (object with "field" and "direction"),'
                    . ' limit (integer).'
                    . ' IMPORTANT: Magento uses "entity_id" as the primary key — never use "id".',
            ],
            steps: [
                'Read the user\'s question and identify what data they need.',
                'Select the most appropriate tool based on the question.',
                'Determine the correct parameters. Use sensible defaults when the user does not specify'
                    . ' (e.g. days=30, limit=10).',
                'Write a single short, friendly sentence to introduce the results.',
            ],
            output: [
                'Respond with ONLY a JSON object — no markdown, no explanation outside the JSON.',
                'The JSON must have exactly three fields: tool_code, parameters_json, explanation.',
                'tool_code: one of order_analytics, customer_lifetime_value, product_performance, query_entity.',
                'parameters_json: a valid JSON-encoded string.'
                    . ' Example: {"analysis_type":"daily_sales","days":30}.'
                    . ' For query_entity sort use {"field":"entity_id","direction":"DESC"}.',
                'explanation: one sentence only. Example: "Here are your daily sales for the last 30 days:".',
                'Never include actual data values, numbers, or analysis in your response.',
            ]
        );
    }
}
