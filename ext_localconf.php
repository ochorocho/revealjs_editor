<?php

declare(strict_types=1);

defined('TYPO3') or die();

// RTE preset registration. Applied via TCA columnsOverrides[bodytext] on each
// container CType in Configuration/TCA/Overrides/tt_content.php.
$GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['revealjs']
    = 'EXT:revealjs_editor/Configuration/RTE/Revealjs.yaml';

$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['reveal'][] = 'Ochorocho\\RevealJsEditor\\ViewHelpers';

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1779100000] = [
    'nodeName' => 'monacoEditor',
    'priority' => 70,
    'class' => \Ochorocho\RevealJsEditor\Form\Element\MonacoEditorElement::class,
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1779100001] = [
    'nodeName' => 'mermaidEditor',
    'priority' => 70,
    'class' => \Ochorocho\RevealJsEditor\Form\Element\MermaidEditorElement::class,
];

// cacheHash whitelist. TYPO3 v14's `typo3 setup` sets
// FE.cacheHash.enforceValidation=true, which 404s ("&cHash empty") any FE URL
// carrying a query param that is neither cHash-signed nor whitelisted. Two of
// our features build such URLs client-side and cannot produce a TYPO3 cHash:
//   - the reveal.js Notes plugin (speaker view, press `S`) loads the deck into
//     two iframes with `receiver`, `progress`, `history`, `transition`,
//     `autoSlide`, `backgroundTransition`, `scrollActivationWidth`, plus
//     `postMessageEvents` (current slide) / `controls` (upcoming slide);
//   - visual_editor's BE preview iframe carries `editMode=1`.
// Whitelisting here (rather than in the dev-only Build/config/system/additional.php)
// ships the fix with the extension so both work on any install.
$cacheHashExcluded = [
    'editMode',
    'receiver',
    'progress',
    'history',
    'transition',
    'autoSlide',
    'backgroundTransition',
    'scrollActivationWidth',
    'postMessageEvents',
    'controls',
];
foreach ($cacheHashExcluded as $param) {
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = $param;
}
