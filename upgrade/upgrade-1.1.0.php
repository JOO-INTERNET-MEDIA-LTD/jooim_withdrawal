<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/** Preserve all existing records and references while installing runtime metadata. */
function upgrade_module_1_1_0($module): bool
{
    return $module->jooimWithdrawalUpgrade();
}
