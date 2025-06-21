<?php
declare(strict_types=1);

/**
 * Controller for manually triggering the order import process from the Magento admin or frontend.
 * Calls the ImportOrder service to process orders from the TEMPOSTAR FTP server.
 */

namespace Tarikul\TempostarConnector\Controller\Index;

use Tarikul\TempostarConnector\Services\ImportOrder;

class Index extends \Magento\Framework\App\Action\Action
{

    protected $_pageFactory;

    /**
     * Index controller constructor.
     *
     * @param ImportOrder $importOrder Service for importing orders from TEMPOSTAR
     * @param \Magento\Framework\App\Action\Context $context Action context for Magento controller
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory Page factory for rendering results
     */
    public function __construct(
        protected ImportOrder $importOrder,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory)
    {
        $this->_pageFactory = $pageFactory;
        return parent::__construct($context);
    }

    /**
     * Executes the order import process when this controller is called.
     *
     * Calls the ImportOrder service to process orders from the TEMPOSTAR FTP server.
     * Typically used for manual triggering from the admin or frontend.
     *
     * @return void
     */
    public function execute()
    {
        $this->importOrder->execute();
    }
}
