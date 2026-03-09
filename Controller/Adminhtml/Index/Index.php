<?php
declare(strict_types=1);

namespace Gtstudio\AiDataQuery\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Data Query Chat backend controller.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Gtstudio_AiDataQuery::management';

    /**
     * Display the Data Query Chat page
     *
     * @return ResultInterface|ResponseInterface
     */
    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Gtstudio_AiDataQuery::chat');
        $resultPage->addBreadcrumb(__('Data Query'), __('Data Query'));
        $resultPage->addBreadcrumb(__('Data Query Chat'), __('Data Query Chat'));
        $resultPage->getConfig()->getTitle()->prepend(__('Data Query Chat'));

        return $resultPage;
    }
}
