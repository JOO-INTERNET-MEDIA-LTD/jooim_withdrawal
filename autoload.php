<?php
/**
 * Module-local autoloader used when Composer's vendor directory is not bundled.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if (!defined('JOOIM_WITHDRAWAL_AUTOLOADER_REGISTERED')) {
    define('JOOIM_WITHDRAWAL_AUTOLOADER_REGISTERED', true);
    spl_autoload_register(static function ($class): void {
        $prefix = 'JooInternetMedia\\PrestaShop\\Module\\JooimWithdrawal\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $direct = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($direct)) {
            require_once $direct;
            return;
        }

        // Compatibility fallback for legacy source folders whose physical path
        // does not exactly match the namespace. Class basenames are unique in
        // this module and the lookup is cached by PHP after require_once.
        $basename = basename(str_replace('\\', '/', $relative)) . '.php';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__ . '/src', FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === $basename) {
                require_once $file->getPathname();
                return;
            }
        }
    });
}


/** JOOIM_WITHDRAWAL_RUNTIME_DIAGNOSTICS_V1 */
if (!class_exists('JooimWithdrawalRuntimeDiagnostics', false)) {
    final class JooimWithdrawalRuntimeDiagnostics
    {
        public static function report(Throwable $exception, string $context, ?string $reference = null): void
        {
            if (!is_string($reference) || preg_match('/^WDERR-[A-Z0-9-]{12,96}$/D', $reference) !== 1) {
                $reference = 'WDERR-' . gmdate('Ymd-His') . '-DIAG-'
                    . strtoupper(substr(hash('sha256', get_class($exception) . '|' . $exception->getMessage() . '|' . $exception->getFile() . '|' . $exception->getLine()), 0, 8));
            }
            $record = [
                'time_utc' => gmdate('c'),
                'reference' => $reference,
                'context' => $context,
                'exception_class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'shop_id' => isset(Context::getContext()->shop->id) ? (int) Context::getContext()->shop->id : null,
                'language_id' => isset(Context::getContext()->language->id) ? (int) Context::getContext()->language->id : null,
            ];
            $line = '[jooim_withdrawal][' . $reference . '][' . $context . '] '
                . $record['exception_class'] . ': ' . $record['message']
                . ' in ' . $record['file'] . ':' . $record['line'];
            if (class_exists('PrestaShopLogger')) {
                try {
                    PrestaShopLogger::addLog($line, 3, null, 'Module', null, true);
                } catch (Throwable $ignored) {
                }
            }

            $directory = __DIR__ . '/var/logs';
            try {
                if (!is_dir($directory)) {
                    @mkdir($directory, 0750, true);
                }
                $path = $directory . '/runtime-' . gmdate('Y-m-d') . '.log';
                if (is_file($path) && filesize($path) > 5 * 1024 * 1024) {
                    @rename($path, $path . '.' . gmdate('His'));
                }
                @file_put_contents(
                    $path,
                    json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
                    FILE_APPEND | LOCK_EX
                );
                @chmod($path, 0640);
            } catch (Throwable $ignored) {
            }
        }
    }
}
