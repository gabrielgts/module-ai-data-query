<?php
declare(strict_types=1);

namespace Gtstudio\AiDataQuery\Block\Adminhtml;

use Gtstudio\AiConnector\Api\TokenCostServiceInterface;
use Gtstudio\AiConnector\Model\Config\ConfigProvider;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class DataQueryChat extends Template
{
    public function __construct(
        Context $context,
        private readonly TokenCostServiceInterface $tokenCostService,
        private readonly ConfigProvider $configProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getChatEndpointUrl(): string
    {
        return $this->_urlBuilder->getUrl('ai_data_query/chat/message');
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    /**
     * Return the pricing table as a JSON-encoded string for injection into JS.
     */
    public function getPricingTableJson(): string
    {
        return (string) json_encode($this->tokenCostService->getPricingTable());
    }

    /**
     * Return the configured model identifier.
     */
    public function getDefaultModel(): string
    {
        return $this->configProvider->getModel();
    }
}
