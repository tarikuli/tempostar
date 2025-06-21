<?php

// Data patch to add an external order reference attribute to sales_order and sales_order_grid tables.
// Used for tracking external system order references in Magento.

namespace Hanesce\TempostarConnector\Setup\Patch\Schema;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;

class AddExternalOrderReference implements SchemaPatchInterface
{
    private $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        $this->moduleDataSetup->getConnection()->addColumn(
            $this->moduleDataSetup->getTable('sales_order'),
            'external_order_reference',
            [
                'type' => Table::TYPE_TEXT,
                'nullable' => true,
                'length' => 255,
                'comment' => 'External Order Reference'
            ]
        );

        $this->moduleDataSetup->getConnection()->addColumn(
            $this->moduleDataSetup->getTable('sales_order_grid'),
            'external_order_reference',
            [
                'type' => Table::TYPE_TEXT,
                'nullable' => true,
                'length' => 255,
                'comment' => 'External Order Reference'
            ]
        );

        $this->moduleDataSetup->endSetup();
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
