<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__DIR__, 2) . '/autoload.php'; // JOOIM_WITHDRAWAL_FRONT_AUTOLOAD

/**
 * Stable, language-neutral public endpoint suitable for PrestaShop Link List.
 *
 * Example:
 * /index.php?fc=module&module=jooim_withdrawal&controller=entry&target=direct
 */
class Jooim_WithdrawalEntryModuleFrontController extends ModuleFrontController
{
    public function __construct()
    {
        parent::__construct();
        $this->auth = false;
        $this->guestAllowed = true;
        $this->ssl = true;
    }

    public function initContent()
    {
        parent::initContent();

        $target = (string) Tools::getValue('target', 'direct');
        if (!in_array($target, ['direct', 'identify'], true)) {
            $target = 'direct';
        }

        $idLang = $this->detectLanguageId();
        $idShop = isset($this->context->shop) ? (int) $this->context->shop->id : null;
        $url = $this->context->link->getModuleLink(
            $this->module->name,
            $target,
            [],
            true,
            $idLang,
            $idShop
        );

        if (!is_string($url) || $url === '') {
            $url = $this->fallbackControllerUrl($target, $idLang);
        }

        Tools::redirect($url);
    }

    private function detectLanguageId(): int
    {
        $idShop = isset($this->context->shop) ? (int) $this->context->shop->id : null;
        $languages = Language::getLanguages(true, $idShop);
        if (!$languages) {
            return (int) Configuration::get('PS_LANG_DEFAULT');
        }

        $byId = [];
        $byCode = [];
        foreach ($languages as $language) {
            $id = (int) $language['id_lang'];
            $iso = strtolower((string) ($language['iso_code'] ?? ''));
            $locale = strtolower(str_replace('_', '-', (string) ($language['locale'] ?? '')));
            $byId[$id] = true;
            if ($iso !== '') {
                $byCode[$iso] = $id;
            }
            if ($locale !== '') {
                $byCode[$locale] = $id;
                $byCode[substr($locale, 0, 2)] = $id;
            }
        }

        // Explicit language is useful for integrations, but only active shop
        // languages are accepted.
        $explicit = strtolower(str_replace('_', '-', (string) Tools::getValue('lang', '')));
        if ($explicit !== '' && isset($byCode[$explicit])) {
            return $byCode[$explicit];
        }

        // Link List is clicked from the current shop page. A same-origin
        // referrer therefore gives the most precise language prefix without
        // hard-coding a language in the saved URL.
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer !== '') {
            $ref = parse_url($referer);
            $currentHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
            $refHost = strtolower((string) ($ref['host'] ?? ''));
            $refHostWithPort = $refHost;
            if (isset($ref['port'])) {
                $refHostWithPort .= ':' . (int) $ref['port'];
            }
            if ($refHost !== '' && ($refHost === preg_replace('/:\d+$/', '', $currentHost) || $refHostWithPort === $currentHost)) {
                $segments = array_values(array_filter(explode('/', trim((string) ($ref['path'] ?? ''), '/'))));
                if (isset($segments[0])) {
                    $code = strtolower(str_replace('_', '-', rawurldecode($segments[0])));
                    if (isset($byCode[$code])) {
                        return $byCode[$code];
                    }
                }
            }
        }

        if (isset($this->context->cookie->id_lang)) {
            $cookieId = (int) $this->context->cookie->id_lang;
            if (isset($byId[$cookieId])) {
                return $cookieId;
            }
        }

        if (isset($this->context->language->id)) {
            $contextId = (int) $this->context->language->id;
            if (isset($byId[$contextId])) {
                return $contextId;
            }
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        foreach (explode(',', $accept) as $candidate) {
            $candidate = trim(explode(';', $candidate, 2)[0]);
            if ($candidate === '') {
                continue;
            }
            if (isset($byCode[$candidate])) {
                return $byCode[$candidate];
            }
            $short = substr($candidate, 0, 2);
            if (isset($byCode[$short])) {
                return $byCode[$short];
            }
        }

        $default = (int) Configuration::get('PS_LANG_DEFAULT');
        return isset($byId[$default]) ? $default : (int) array_key_first($byId);
    }

    private function fallbackControllerUrl(string $target, int $idLang): string
    {
        $idShop = isset($this->context->shop) ? (int) $this->context->shop->id : null;
        $base = method_exists($this->context->link, 'getBaseLink')
            ? (string) $this->context->link->getBaseLink($idShop, true)
            : Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__;

        return rtrim($base, '/') . '/index.php?' . http_build_query([
            'fc' => 'module',
            'module' => $this->module->name,
            'controller' => $target,
            'id_lang' => $idLang,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
