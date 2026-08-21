<?php
/**
 * Release autoloader for the module's own PSR-4 namespace.
 * No third-party dependencies are bundled by this module.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'JooInternetMedia\\PrestaShop\\Module\\JooimWithdrawal\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
