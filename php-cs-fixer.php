<?php

declare(strict_types=1);

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->getFinder()
    ->in([
        __DIR__ . '/Classes',
        __DIR__ . '/Configuration',
        __DIR__ . '/Tests',
    ])
    ->append([
        __DIR__ . '/ext_localconf.php',
    ]);
return $config;
