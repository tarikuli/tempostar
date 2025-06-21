<?php
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

/**
 * Registers the Tarikul_TempostarConnector module with Magento 2.
 *
 * This function call is required for Magento to recognize and enable the module during setup and runtime.
 *
 * @see https://devdocs.magento.com/guides/v2.4/extension-dev-guide/build/module-file-structure.html#registrationphp
 */
ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Tarikul_TempostarConnector',
    __DIR__,
);
