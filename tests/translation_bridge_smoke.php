<?php
/**
 * Regression test for PrestaShop 8 where ModuleCore::trans() is protected.
 */
declare(strict_types=1);

define('_PS_VERSION_', '8.2.3');

class Module
{
    public string $name = '';
    public string $tab = '';
    public string $version = '';
    public string $author = '';
    public int $need_instance = 0;
    public bool $bootstrap = false;
    public array $ps_versions_compliancy = [];
    public string $displayName = '';
    public string $description = '';

    public function __construct()
    {
    }

    protected function trans($id, array $parameters = [], $domain = null, $locale = null): string
    {
        unset($domain, $locale);
        return strtr((string) $id, $parameters);
    }
}

class ModuleFrontController
{
    public $module;
}

require_once dirname(__DIR__) . '/jooim_withdrawal.php';

$module = new Jooim_Withdrawal();
$result = $module->jooimTranslate('Hello %name%', ['%name%' => 'PrestaShop 8'], 'Modules.Jooimwithdrawal.Shop');
if ($result !== 'Hello PrestaShop 8') {
    fwrite(STDERR, "FAIL: public translation bridge did not delegate to protected ModuleCore::trans()\n");
    exit(1);
}

require_once dirname(__DIR__) . '/src/Compatibility/FrontControllerBase.php';

final class BridgeFrontController extends JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Compatibility\FrontControllerBase
{
    public function __construct(Jooim_Withdrawal $module)
    {
        $this->module = $module;
    }

    public function translatedStatement(): string
    {
        return $this->statement();
    }
}

$frontController = new BridgeFrontController($module);
if ($frontController->translatedStatement() === '') {
    fwrite(STDERR, "FAIL: FrontControllerBase could not translate through the public bridge\n");
    exit(1);
}

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__), FilesystemIterator::SKIP_DOTS)) as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    if ($file->getFilename() === 'jooim_withdrawal.php') {
        continue;
    }
    $source = (string) file_get_contents($file->getPathname());
    if (preg_match('/(?:\\$this->module|\\$module)->trans\\s*\\(/', $source) === 1) {
        fwrite(STDERR, 'FAIL: protected translation call remains in ' . $file->getPathname() . "\n");
        exit(1);
    }
}

echo "PASS: PrestaShop 8 public translation bridge\n";
