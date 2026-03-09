<?php

declare(strict_types=1);

namespace Gtstudio\AiDataQuery\Setup\Patch\Data;

use Gtstudio\AiAgents\Api\GetAiAgentByCodeInterface;
use Gtstudio\AiAgents\Api\SaveAiAgentInterface;
use Gtstudio\AiAgents\Model\Data\AiAgentData;
use Gtstudio\AiAgents\Model\Data\AiAgentDataFactory;
use Gtstudio\AiDataQuery\Setup\Patch\Data\BootstrapPhase2SpecializedTools;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class CreateDataQueryAgent implements DataPatchInterface
{
    private const AGENT_CODE = 'data_query';

    private const TOOL_CODES = [
        'query_entity',
        'order_analytics',
        'customer_lifetime_value',
        'product_performance',
    ];

    public function __construct(
        private readonly GetAiAgentByCodeInterface $getAiAgentByCode,
        private readonly SaveAiAgentInterface $saveAiAgent,
        private readonly AiAgentDataFactory $agentFactory,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function apply(): self
    {
        if ($this->agentExists()) {
            return $this;
        }

        /** @var AiAgentData $agent */
        $agent = $this->agentFactory->create();
        $agent->setCode(self::AGENT_CODE);
        $agent->setDescription(
            'Full-page analytics assistant that answers natural language questions about store data ' .
            'by running SQL-backed tools against orders, customers, and products.'
        );
        $agent->setBackground(
            "You are a Magento store data analyst.\n" .
            "You have access to tools that query real store data: orders, customers, products, and analytics.\n" .
            "Always use the available tools to fetch accurate, up-to-date data before answering.\n" .
            "Never make up numbers — only report what the tools return.\n" .
            "Present results clearly, using tables or bullet points when showing lists of data."
        );
        $agent->setSteps(
            "Understand what data the user is asking about (orders, customers, products, inventory, revenue, etc.).\n" .
            "Choose the most appropriate tool for the request.\n" .
            "Call the tool with the correct parameters.\n" .
            "Interpret and summarize the returned data in plain language.\n" .
            "If the result is empty or incomplete, say so clearly and suggest alternatives."
        );
        $agent->setOutput(
            "Lead with a direct answer or summary sentence.\n" .
            "Use markdown tables for lists of records or metrics.\n" .
            "Use bullet points for short categorical breakdowns.\n" .
            "Include relevant numbers, percentages, or trends where available.\n" .
            "Keep responses concise — do not repeat raw data verbatim if a summary suffices."
        );

        $toolIds = $this->resolveToolIds();
        if (!empty($toolIds)) {
            $agent->setTools(implode(',', $toolIds));
        }

        $this->saveAiAgent->execute($agent);

        return $this;
    }

    public static function getDependencies(): array
    {
        return [
            BootstrapPhase2SpecializedTools::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }

    private function agentExists(): bool
    {
        try {
            $this->getAiAgentByCode->execute(self::AGENT_CODE);
            return true;
        } catch (NoSuchEntityException) {
            return false;
        }
    }

    private function resolveToolIds(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('gtstudio_ai_tools');

        $select = $connection->select()
            ->from($table, ['entity_id'])
            ->where('code IN (?)', self::TOOL_CODES);

        return $connection->fetchCol($select);
    }
}
