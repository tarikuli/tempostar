<?php
declare(strict_types=1);

namespace Tarikul\TempostarConnector\Model;

/**
 * This model provides access to configuration values for the TempostarConnector module.
 * It retrieves FTP, order, and inventory settings from Magento's configuration system.
 */

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;

class Config
{
    const XML_PATH_TEMPOSTAR_CONNECTOR_ENABLE = 'tempostar_ftp_section/tempostar_ftp_group/enable';
    const XML_PATH_TEMPOSTAR_CONNECTOR_HOST = 'tempostar_ftp_section/tempostar_ftp_group/host';
    const XML_PATH_TEMPOSTAR_CONNECTOR_USERNAME = 'tempostar_ftp_section/tempostar_ftp_group/ftp_user_name';
    const XML_PATH_TEMPOSTAR_CONNECTOR_PASSWORD = 'tempostar_ftp_section/tempostar_ftp_group/ftp_user_pass';
    const XML_PATH_TEMPOSTAR_CONNECTOR_PORT = 'tempostar_ftp_section/tempostar_ftp_group/port';
    const XML_PATH_ORDER_PATH = 'tempostar_ftp_section/order_group/order_path';
    const XML_PATH_ORDER_ARCHIVE_PATH = 'tempostar_ftp_section/order_group/order_archive_path';
    const XML_PATH_ORDER_UPDATE_PATH = 'tempostar_ftp_section/order_group/order_update_path';
    const XML_PATH_ORDER_UPDATE_ARCHIVE_PATH = 'tempostar_ftp_section/order_group/order_update_archive_path';
    const XML_PATH_ORDER_SCHEDULE = 'tempostar_ftp_section/order_group/order_schedule';
    const XML_PATH_EXPORT_FULFILLMENT_PATH = 'tempostar_ftp_section/export_fulfillment_group/export_fulfillment_path';
    const XML_PATH_INVENTORY_PATH = 'tempostar_ftp_section/inventory_group/inventory_path';
    const XML_PATH_INVENTORY_SCHEDULE = 'tempostar_ftp_section/inventory_group/inventory_schedule';
    const MAGE_INVENTORY_MANAGE = 'cataloginventory/options/can_subtract';
    const CUSTOM_INVENTORY_MANAGE = 'cataloginventory/options/custom_can_subtract';

    /**
     * Config constructor.
     *
     * @param ScopeConfigInterface $scopeConfig Magento configuration interface
     * @param Json $serializer JSON serializer for config values
     */
    public function __construct(
        protected ScopeConfigInterface $scopeConfig,
        protected Json $serializer
    ) {}

    /**
     * Check if the TEMPOSTAR connector is enabled in Magento configuration.
     *
     * @return bool True if enabled, false otherwise
     */
    public function isTempostarConnectorEnabled()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_TEMPOSTAR_CONNECTOR_ENABLE, ScopeInterface::SCOPE_STORE);
    }


    /**
     * Get TEMPOSTAR FTP host from configuration.
     *
     * @return string FTP host
     */
    public function getTempostarHost()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_TEMPOSTAR_CONNECTOR_HOST, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get TEMPOSTAR FTP username from configuration.
     *
     * @return string FTP username
     */
    public function getTempostarUsername()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_TEMPOSTAR_CONNECTOR_USERNAME, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get TEMPOSTAR FTP password from configuration.
     *
     * @return string FTP password
     */
    public function getTempostarPassword()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_TEMPOSTAR_CONNECTOR_PASSWORD, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get TEMPOSTAR port from configuration.
     *
     * @return string TEMPOSTAR port
     */
    public function getTempostarPort()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_TEMPOSTAR_CONNECTOR_PORT, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get Order Import Path
     *
     * @return mixed
     */
    public function getOrderImportPath()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_ORDER_PATH);
    }

    /**
     * Get Order Import Archive Path
     *
     * @return mixed
     */
    public function getOrderImportArchivePath()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_ORDER_ARCHIVE_PATH);
    }

    /**
     * @return mixed
     */
    public function getOrderUpdatePath()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_ORDER_UPDATE_PATH);
    }

    /**
     * @return mixed
     */

    public function getOrderUpdateArchivePath()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_ORDER_UPDATE_ARCHIVE_PATH);
    }

    /**
     * Get Order Import Schedule
     *
     * @return mixed
     */
    public function getOrderImportSchedule()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_ORDER_SCHEDULE);
    }

    /**
     * Get Export Fulfillment Path
     *
     * @return mixed
     */
    public function getExportFulfillmentPath()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_EXPORT_FULFILLMENT_PATH);
    }


    /**
     * Get Inventory Path
     *
     * @return mixed
     */
    public function getInventoryPath()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_INVENTORY_PATH);
    }

    /**
     * Get Inventory Schedule
     *
     * @return mixed
     */
    public function getInventorySchedule()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_INVENTORY_SCHEDULE);
    }

    /**
     * Get Magento default inventory management status
     *
     * @return mixed
     */
    public function getBaseInventoryManagement()
    {
        return $this->scopeConfig->getValue(
            self::MAGE_INVENTORY_MANAGE,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Custom inventory management status
     *
     * @return mixed
     */
    public function getCustomInventoryManagement()
    {
        return $this->scopeConfig->getValue(
            self::CUSTOM_INVENTORY_MANAGE,
            ScopeInterface::SCOPE_STORE
        );
    }
}
