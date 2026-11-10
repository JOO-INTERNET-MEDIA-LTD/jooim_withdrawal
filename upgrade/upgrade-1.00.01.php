<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/** Preserve retained records while synchronizing the existing runtime integration. */
function upgrade_module_1_00_01($module): bool
{
    return $module->jooimWithdrawalUpgrade();
}
