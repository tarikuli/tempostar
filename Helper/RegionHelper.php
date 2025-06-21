<?php

// Helper class for region-related database lookups, such as finding region IDs by name and locale.
// Used to support address and order data processing in the TempostarConnector module.

namespace Tarikul\TempostarConnector\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DB\Adapter\AdapterInterface;

class RegionHelper extends AbstractHelper
{
    /**
     * @var AdapterInterface
     */
    protected $connection;

    /**
     * RegionHelper constructor.
     *
     * @param Context $context Magento helper context
     * @param \Magento\Framework\App\ResourceConnection $resource Resource connection for DB access
     */
    public function __construct(
        Context $context,
        \Magento\Framework\App\ResourceConnection $resource
    ) {
        parent::__construct($context);
        $this->connection = $resource->getConnection();
    }

    /**
     * Get region_id by region name and locale.
     *
     * Looks up the region_id in the database for a given region name and country locale.
     * Used for address normalization and order import.
     *
     * @param string $regionName The name of the region (e.g., prefecture)
     * @param string $locale The country code (e.g., 'JP')
     * @return int|null The region_id if found, otherwise null
     */
    public function getRegionIdByNameAndLocale($regionName, $locale)
    {
        $select = $this->connection->select()
            ->from(['region' => $this->connection->getTableName('directory_country_region')], ['region_id'])
            ->joinLeft(
                ['region_name' => $this->connection->getTableName('directory_country_region_name')],
                'region.region_id = region_name.region_id',
                []
            )
            ->where('region_name.name = ?', $regionName)
            ->where('region.country_id = ?', $locale);
        return $this->connection->fetchOne($select);
    }
}
