<?php
/**
 * Regression gate for the final POST controller lifecycle.
 *
 * The legal database transaction must run in postProcess(), before PrestaShop
 * initializes the storefront header and executes third-party displayHeader
 * hooks. The action then redirects to a normal GET page.
 */

$root = dirname(__DIR__);
$failures = [];
$passes = [];

$assert = static function (bool $condition, string $label) use (&$failures, &$passes): void {
    if ($condition) {
        $passes[] = $label;
    } else {
        $failures[] = $label;
    }
};

/**
 * Extract a PHP method body using a small brace-aware scanner.
 */
function methodBody(string $source, string $method): string
{
    if (!preg_match('/function\s+' . preg_quote($method, '/') . '\s*\([^)]*\)\s*\{/m', $source, $match, PREG_OFFSET_CAPTURE)) {
        return '';
    }

    $start = $match[0][1] + strlen($match[0][0]);
    $depth = 1;
    $length = strlen($source);
    $quote = null;
    $escaped = false;

    for ($i = $start; $i < $length; $i++) {
        $char = $source[$i];
        if ($quote !== null) {
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                continue;
            }
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($char === "'" || $char === '"') {
            $quote = $char;
            continue;
        }
        if ($char === '{') {
            $depth++;
        } elseif ($char === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $start, $i - $start);
            }
        }
    }

    return '';
}

$controllers = [
    'order-reference submit' => $root . '/controllers/front/submit.php',
    'direct submit' => $root . '/controllers/front/directsubmit.php',
];

foreach ($controllers as $label => $path) {
    $source = (string) file_get_contents($path);
    $init = methodBody($source, 'init');
    $post = methodBody($source, 'postProcess');
    $content = methodBody($source, 'initContent');

    $assert($init !== '', $label . ': init method exists');
    $assert($post !== '', $label . ': postProcess method exists');
    $assert($content !== '', $label . ': initContent fallback exists');
    $assert(strpos($init, 'displayHeader(false)') !== false, $label . ': header disabled before setMedia');
    $assert(strpos($init, 'displayFooter(false)') !== false, $label . ': footer disabled on action endpoint');
    $assert(strpos($init, 'displayHeaderJavaScript(false)') !== false, $label . ': header JavaScript disabled on action endpoint');
    $assert(strpos($post, '->withdrawals()->submit(') !== false, $label . ': transaction runs in postProcess');
    $assert(strpos($post, 'getForSubmission(') !== false, $label . ': server-side draft is reloaded');
    $assert(strpos($post, 'setRedirectAfter(') !== false, $label . ': Post/Redirect/Get is scheduled');
    $assert(strpos($post, 'reportFrontFailure(') !== false, $label . ': exceptions are recorded without rendering');
    $assert(strpos($post, 'submissionFailureUrl(') !== false, $label . ': failures redirect to a normal GET page');
    $assert(strpos($content, '->withdrawals()->submit(') === false, $label . ': initContent cannot create a withdrawal');
    $assert(strpos($content, 'Tools::redirect(') !== false, $label . ': direct access to action endpoint is redirected');
    $assert(strpos($source, '$this->runPage(') === false, $label . ': displayHeader is never initialized before the transaction');
    $assert(strpos($source, 'JOOIM_WITHDRAWAL_POST_REDIRECT_GET_V3') !== false, $label . ': lifecycle revision marker present');
}

$base = (string) file_get_contents($root . '/src/Compatibility/FrontControllerBase.php');
$identify = (string) file_get_contents($root . '/controllers/front/identify.php');
$direct = (string) file_get_contents($root . '/controllers/front/direct.php');
$directTemplate = (string) file_get_contents($root . '/views/templates/front/direct.tpl');

$assert(strpos($base, 'function reportFrontFailure') !== false, 'shared non-rendering error reporter exists');
$assert(strpos($base, 'function submissionFailureUrl') !== false, 'safe GET redirect builder exists');
$assert(strpos($base, 'function submissionErrorMessage') !== false, 'safe GET error resolver exists');
$assert(strpos($base, "return 'EXT';") !== false, 'external-module failures are classified separately');
$assert(strpos($identify, '$this->submissionErrorMessage()') !== false, 'identify page renders redirected errors');
$assert(strpos($direct, '$this->submissionErrorMessage()') !== false, 'direct page renders redirected errors');
$assert(strpos($directTemplate, '$submission_error') !== false, 'direct template contains redirected-error alert');

if ($failures !== []) {
    echo 'FAIL: ' . count($failures) . PHP_EOL;
    foreach ($failures as $failure) {
        echo ' - ' . $failure . PHP_EOL;
    }
    exit(1);
}

echo 'PASS: ' . count($passes) . " final-POST lifecycle checks\n";
