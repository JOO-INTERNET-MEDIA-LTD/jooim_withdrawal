<?php
/**
 * Regression gate for the final withdrawal submission pipeline.
 *
 * It protects the transaction against the PrestaShop 8 getRow()/getValue()
 * LIMIT-appending behaviour, verifies escaped JSON writes and ensures the
 * final POST cannot bypass the shared blank-page guard.
 */

namespace {
    if (!defined('_DB_PREFIX_')) {
        define('_DB_PREFIX_', 'ps_');
    }

    if (!function_exists('pSQL')) {
        function pSQL($value, $htmlOk = false)
        {
            return addslashes((string) $value);
        }
    }

    final class Db
    {
        public static ?self $instance = null;
        public array $executeQueries = [];
        public array $selectQueries = [];
        public array $updates = [];
        private string $error = '';

        public static function getInstance($master = true): self
        {
            if (!self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function execute($sql, $useCache = true): bool
        {
            $this->executeQueries[] = (string) $sql;
            return true;
        }

        public function executeS($sql, $array = true, $useCache = true)
        {
            $this->selectQueries[] = ['sql' => (string) $sql, 'use_cache' => (bool) $useCache];
            return [['last_value' => 41]];
        }

        public function update($table, $data, $where = '', $limit = 0, $nullValues = false, $useCache = true, $addPrefix = true): bool
        {
            $this->updates[] = compact('table', 'data', 'where', 'limit', 'nullValues', 'useCache', 'addPrefix');
            return true;
        }

        public function getMsgError(): string
        {
            return $this->error;
        }
    }
}

namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service {
    final class ModuleConfig
    {
        public function get(string $key, string $default = ''): string
        {
            return $default;
        }
    }
}

namespace {
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

    $submission = (string) file_get_contents($root . '/src/Service/WithdrawalSubmissionService.php');
    $reference = (string) file_get_contents($root . '/src/Service/WithdrawalReferenceGenerator.php');
    $drafts = (string) file_get_contents($root . '/src/Service/WithdrawalDraftService.php');
    $submit = (string) file_get_contents($root . '/controllers/front/submit.php');
    $directSubmit = (string) file_get_contents($root . '/controllers/front/directsubmit.php');
    $confirmation = (string) file_get_contents($root . '/controllers/front/confirmation.php');
    $identify = (string) file_get_contents($root . '/controllers/front/identify.php');
    $direct = (string) file_get_contents($root . '/controllers/front/direct.php');
    $directTemplate = (string) file_get_contents($root . '/views/templates/front/direct.tpl');
    $base = (string) file_get_contents($root . '/src/Compatibility/FrontControllerBase.php');

    $lockingSources = $submission . "\n" . $reference;
    $lockingCode = preg_replace('~/\*.*?\*/|//[^\r\n]*~s', '', $lockingSources) ?? $lockingSources;
    $unsafeLockingStatement = preg_match(
        '/->(?:getRow|getValue)\s*\([^;]*FOR UPDATE/s',
        $lockingCode
    ) === 1;

    $assert(!$unsafeLockingStatement, 'no FOR UPDATE query uses Db::getRow() or Db::getValue()');
    $assert(substr_count($lockingCode, ' FOR UPDATE') === 3, 'draft, order and reference sequence use three locking reads');
    $assert(strpos($submission, '$rows = $db->executeS($sql, true, false);') !== false, 'submission locking helper uses executeS without cache');
    $assert(strpos($submission, "'lock_draft'") !== false && strpos($submission, "'lock_order'") !== false, 'draft and order locks pass through the safe helper');
    $assert(preg_match('/SELECT last_value.*?FOR UPDATE.*?true,\s*false\s*\);/s', $reference) === 1, 'reference sequence lock uses executeS without cache');


    $assert(strpos($submission, "'payload_json' => pSQL(\$snapshotJson, true)") !== false, 'immutable payload JSON is SQL escaped');
    $assert(strpos($submission, "'snapshot_json' => pSQL(\$itemSnapshot, true)") !== false, 'item snapshot JSON is SQL escaped');
    $assert(strpos($submission, "'metadata_json' => pSQL(\$historyMetadata, true)") !== false, 'history JSON is SQL escaped');
    $assert(strpos($submission, "'payload_json' => pSQL(\$outboxPayload, true)") !== false, 'outbox JSON is SQL escaped');
    $assert(strpos($drafts, "'draft_payload' => pSQL(\$payloadJson, true)") !== false, 'draft JSON is SQL escaped');

    $assert(strpos($submission, 'mustInsert(') !== false, 'transactional inserts are checked');
    $assert(strpos($submission, 'mustUpdate(') !== false, 'transactional updates are checked');
    $assert(strpos($submission, 'mustExecute(') !== false, 'transaction begin and commit are checked');
    $assert(strpos($submission, "'commit_submission'") !== false, 'submission commit has a diagnostic stage');
    $assert(strpos($submission, 'postCommitStage(') !== false, 'PDF, mail and hooks are isolated after commit');
    $assert(strpos($submission, 'must never turn an accepted withdrawal into a blank page') !== false, 'accepted submission is explicitly protected from post-commit failures');
    $assert(strpos($submission, '(int) $detail->id_order !== $idOrder') !== false, 'selected order detail ownership is revalidated');
    $assert(preg_match('/->(?:getRow|getValue)\s*\([^;]*LIMIT\s+[0-9]+/si', $submission . "\n" . $drafts . "\n" . $reference) !== 1, 'getRow/getValue calls do not supply their own LIMIT clause');

    $assert(strpos($confirmation, '$this->runPage(') !== false, 'confirmation page uses the shared failure guard');
    foreach ([$submit, $directSubmit] as $index => $controller) {
        $label = 'submit controller ' . ($index + 1);
        $postProcessPosition = strpos($controller, 'public function postProcess()');
        $initContentPosition = strpos($controller, 'public function initContent()');
        $submitCallPosition = strpos($controller, '->withdrawals()->submit(');

        $assert($postProcessPosition !== false, $label . ' processes the final action in postProcess');
        $assert($initContentPosition !== false && $postProcessPosition < $initContentPosition, $label . ' processes POST before content rendering');
        $assert($submitCallPosition !== false && $submitCallPosition > $postProcessPosition && $submitCallPosition < $initContentPosition, $label . ' commits inside postProcess');
        $assert(strpos($controller, 'JOOIM_WITHDRAWAL_POST_REDIRECT_GET_V3') !== false, $label . ' carries the PRG lifecycle revision marker');
        $assert(strpos($controller, '$this->displayHeader(false);') !== false, $label . ' disables storefront header rendering');
        $assert(strpos($controller, '$this->displayFooter(false);') !== false, $label . ' disables storefront footer rendering');
        $assert(strpos($controller, '$this->displayHeaderJavaScript(false);') !== false, $label . ' disables storefront header JavaScript rendering');
        $assert(strpos($controller, '$this->setRedirectAfter(') !== false, $label . ' schedules a redirect before displayHeader');
        $assert(strpos($controller, '$this->runPage(') === false, $label . ' does not initialize theme content before POST processing');
        $assert(strpos($controller, 'parent::initContent()') === false, $label . ' never invokes displayHeader from the action endpoint');
        $assert(strpos($controller, 'getForSubmission(') !== false, $label . ' supports idempotent retry');
        $assert(strpos($controller, 'executeWithoutOutput(') !== false, $label . ' protects redirect headers');
        $assert(strpos($controller, 'reportFrontFailure(') !== false, $label . ' records a technical failure before redirecting');
        $assert(strpos($controller, 'submissionFailureUrl(') !== false, $label . ' redirects failures to a normal GET page');
        $assert(strpos($controller, "'message_text' => \$e->getMessage()") === false, $label . ' never exposes raw exception details');
    }
    $assert(strpos($base, 'protected function executeWithoutOutput') !== false, 'shared output-isolation helper exists');
    $assert(strpos($base, 'protected function reportFrontFailure') !== false, 'shared non-rendering failure reporter exists');
    $assert(strpos($base, 'protected function submissionFailureUrl') !== false, 'shared safe failure redirect builder exists');
    $assert(strpos($base, 'protected function submissionErrorMessage') !== false, 'normal GET pages can display a safe post-submit error');
    $assert(strpos($base, "return 'EXT';") !== false, 'third-party module hook failures receive the EXT component');
    $assert(strpos($identify, '$this->submissionErrorMessage()') !== false, 'order-reference page displays redirected submission errors');
    $assert(strpos($direct, "'submission_error' => \$this->submissionErrorMessage()") !== false, 'direct page displays redirected submission errors');
    $assert(strpos($directTemplate, '$submission_error') !== false, 'direct template renders the safe redirected error message');
    $assert(strpos($drafts, 'public function getForSubmission') !== false, 'submitted drafts can be resolved idempotently');
    $assert(strpos($drafts, 'submitted_at IS NOT NULL OR expires_at > UTC_TIMESTAMP()') !== false, 'idempotent draft query permits an already committed submission');

    require_once $root . '/src/Service/WithdrawalReferenceGenerator.php';
    $generator = new \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\WithdrawalReferenceGenerator(
        new \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\ModuleConfig()
    );
    $generated = $generator->next(1, 'SK');
    $db = \Db::getInstance();
    $assert($generated === sprintf('WD-SK-%04d-000042', (int) gmdate('Y')), 'reference generator advances the locked sequence');
    $assert(isset($db->selectQueries[0]['sql']) && str_ends_with($db->selectQueries[0]['sql'], 'FOR UPDATE'), 'reference locking query ends with FOR UPDATE');
    $assert(isset($db->selectQueries[0]['use_cache']) && $db->selectQueries[0]['use_cache'] === false, 'reference locking query bypasses cache');
    $assert(isset($db->updates[0]['data']['last_value']) && (int) $db->updates[0]['data']['last_value'] === 42, 'reference sequence update persists the next value');

    if ($failures !== []) {
        echo 'FAIL: ' . count($failures) . PHP_EOL;
        foreach ($failures as $failure) {
            echo ' - ' . $failure . PHP_EOL;
        }
        exit(1);
    }

    echo 'PASS: ' . count($passes) . " submission pipeline checks\n";
}
