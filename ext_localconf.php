<?php

declare(strict_types=1);

defined('TYPO3') or die();

// RTE preset registration. Applied via TCA columnsOverrides[bodytext] on each
// container CType in Configuration/TCA/Overrides/tt_content.php.
$GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['revealjs']
    = 'EXT:revealjs_editor/Configuration/RTE/Revealjs.yaml';

$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['reveal'][] = 'Ochorocho\\RevealJsEditor\\ViewHelpers';
