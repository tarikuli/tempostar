<?php

// Data patch for installing custom order statuses required by the TempostarConnector module.
// Adds statuses like 'waiting_for_confirmation', 'waiting_for_shipment', and 'shipped' to Magento.

namespace Tarikul\TempostarConnector\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Sales\Model\Order\Status;

class InstallData implements InstallDataInterface
{
    protected $statusFactory;

    public function __construct(StatusFactory $statusFactory)
    {
        $this->statusFactory = $statusFactory;
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $statuses = [
            'waiting_for_confirmation' => 'Waiting for Confirmation',
            'waiting_for_shipment' => 'Waiting for Shipment',
            'shipped' => 'Shipped'
        ];

        foreach ($statuses as $statusCode => $statusLabel) {
            /** @var Status $status */
            $status = $this->statusFactory->create();
            $status->setData([
                'status' => $statusCode,
                'label' => $statusLabel
            ])->save();

            // Assign each status to the "new" state
            $status->assignState('new', false, true);
        }
    }
}
