# Tarikul_TempostarConnector Magento 2 Module

## Overview
Tarikul_TempostarConnector is a Magento 2 module designed to automate the integration between your Magento store and the TEMPOSTAR ERP system.

**About TEMPOSTAR:**

![TEMPOSTAR System Overview](https://commerce-star.com/wp-content/themes/tempostar/assets/images/about/about_top_figure01.png)

[TEMPOSTAR](https://commerce-star.com/about/) is a leading Japanese unified eCommerce management platform (一元管理システム) that enables centralized management of orders, inventory, products, and customer data across multiple online stores and sales channels. It is an ASP-based (cloud) system with hybrid customization capabilities, allowing both small and large businesses to start with standard features and expand with custom development as their needs grow. TEMPOSTAR is designed to streamline operations, reduce manual work, and scale business efficiently, supporting both standard and highly customized workflows.

**Key features and advantages of TEMPOSTAR:**
- Centralized management of multiple online stores, EC malls, and sales channels from a single dashboard
- Automated order, inventory, and product data synchronization
- Flexible, scalable, and highly customizable to fit unique business processes
- Consistent, unified interface for all operations, reducing training and operational errors
- Multi-warehouse and multi-location inventory management
- Robust support for Japanese eCommerce requirements, including custom order statuses and address attributes
- Always up-to-date with the latest EC platform changes and high security standards
- Total support, including onboarding, troubleshooting, and ongoing operations
- Trusted by a wide range of Japanese eCommerce businesses, from small shops to large enterprises

**Why integrate Magento with TEMPOSTAR?**
By connecting Magento with TEMPOSTAR, this module helps merchants:
- Eliminate manual CSV uploads/downloads and reduce operational errors
- Automate order and inventory synchronization between Magento and TEMPOSTAR
- Adapt to business growth and changing needs without switching systems
- Enhance business efficiency through automation and unified workflows
- Leverage the full power of TEMPOSTAR’s centralized management for all sales channels

This module provides scheduled and manual import/export of order and inventory data via SFTP using CSV files, enabling seamless data synchronization between Magento and external business systems managed by TEMPOSTAR.

## Features
- Scheduled and manual import of orders from TEMPOSTAR via SFTP (CSV format)
- Scheduled and manual export of inventory data to TEMPOSTAR via SFTP (CSV format)
- Custom order statuses and address attributes for Japanese eCommerce needs
- Robust logging for all import/export operations
- Admin configuration for FTP credentials and scheduling

## Installation
1. **Copy the module files**
   - Place the `Tarikul/TempostarConnector` directory into `app/code/Tarikul/` in your Magento installation.

2. **Enable the module**
   ```zsh
   bin/magento module:enable Tarikul_TempostarConnector
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento cache:flush
   ```

3. **Set permissions** (if needed)
   ```zsh
   chmod -R 777 var/ pub/static/ generated/
   ```

## Configuration
1. Log in to the Magento Admin Panel.
2. Go to `Stores > Configuration > Integration > TEMPOSTAR Connector`.
3. Enter your FTP credentials, paths, and enable the module.
4. Set up cron schedules for order import and inventory export as needed.

## Usage
### Automatic (Cron)
- The module will automatically import orders and export inventory based on the cron schedule you set in the admin configuration.

### Manual (CLI)
- **Import Orders:**
  ```zsh
  bin/magento tempostarconnector:order:import
  ```
- **Export Inventory:**
  ```zsh
  bin/magento tempostarconnector:inventory:export
  ```

### Manual (Admin Controller)
- You can also trigger imports from the Magento admin or via a custom controller endpoint if needed.

## Logging
- All import/export operations and errors are logged to `var/log/tempostar/` for easy troubleshooting.

## Uninstallation
1. Disable the module:
   ```zsh
   bin/magento module:disable Tarikul_TempostarConnector
   bin/magento setup:upgrade
   ```
2. Remove the module files from `app/code/Tarikul/TempostarConnector`.

## Support
For issues or feature requests, please contact the module maintainer or your development team.

---
**Note:** This module is intended for use by Magento 2 developers and system administrators familiar with custom module management and SFTP integrations.
