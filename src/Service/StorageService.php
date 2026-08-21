<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

final class StorageService
{
    public function baseDir(): string
    {
        return rtrim(_PS_ROOT_DIR_, '/\\') . '/var/private/jooim_withdrawal/documents';
    }

    public function ensureStorage(): void
    {
        $dir = $this->baseDir();
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create private withdrawal document storage: ' . $dir);
        }
        @file_put_contents(dirname($dir) . '/.htaccess', "Require all denied\nDeny from all\n");
        @file_put_contents(dirname($dir) . '/index.php', "<?php http_response_code(404); exit;\n");
        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        @file_put_contents($dir . '/index.php', "<?php http_response_code(404); exit;\n");
    }

    public function writePdf(string $reference, string $content): array
    {
        $this->ensureStorage();
        $safeRef = preg_replace('/[^A-Za-z0-9_.-]/', '_', $reference);
        $filename = $safeRef . '-' . bin2hex(random_bytes(12)) . '.pdf';
        $path = $this->baseDir() . '/' . $filename;
        if (@file_put_contents($path, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Cannot write withdrawal PDF.');
        }
        @chmod($path, 0640);
        return [
            'path' => $path,
            'size' => filesize($path) ?: strlen($content),
            'hash' => hash('sha256', $content),
            'mime' => 'application/pdf',
        ];
    }

    public function read(string $path): string
    {
        $realBase = realpath($this->baseDir());
        $realPath = realpath($path);
        if ($realBase === false || $realPath === false || strncmp($realPath, $realBase . DIRECTORY_SEPARATOR, strlen($realBase) + 1) !== 0) {
            throw new \RuntimeException('Invalid document path.');
        }
        $content = @file_get_contents($realPath);
        if ($content === false) {
            throw new \RuntimeException('Document cannot be read.');
        }
        return $content;
    }
}
