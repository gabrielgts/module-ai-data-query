<?php

declare(strict_types=1);

namespace Gtstudio\AiDataQuery\Controller\Adminhtml\Chat;

use Gtstudio\AiDataQuery\Model\Service\DataQueryChatService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class Message extends Action
{
    public const ADMIN_RESOURCE = 'Gtstudio_AiDataQuery::management';

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param DataQueryChatService $chatService
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly DataQueryChatService $chatService
    ) {
        parent::__construct($context);
    }

    /**
     * Handle the data query chat AJAX request.
     *
     * Only the user's message is forwarded to the service. Conversation history
     * is intentionally not passed — each query is an independent LLM intent
     * extraction step that never receives or returns store data.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $message = trim((string) $this->getRequest()->getParam('message'));

        if (empty($message)) {
            return $this->resultJsonFactory->create()->setData([
                'success' => false,
                'content' => ''
            ]);
        }

        try {
            $response = $this->chatService->ask($message);

            $result = [
                'success' => true,
                'content' => $response['content'] ?? '',
            ];

            if (!empty($response['data'])) {
                $result['data'] = $response['data'];
            }
            if (!empty($response['tool'])) {
                $result['tool'] = $response['tool'];
            }
            if (isset($response['tokens'])) {
                $result['tokens'] = (int) $response['tokens'];
            }
            if (!empty($response['model'])) {
                $result['model'] = $response['model'];
            }
            if (!empty($response['provider'])) {
                $result['provider'] = $response['provider'];
            }

            return $this->resultJsonFactory->create()->setData($result);
        } catch (\Throwable $e) {
            return $this->resultJsonFactory->create()->setData([
                'success' => false,
                'content' => $e->getMessage()
            ]);
        }
    }
}
