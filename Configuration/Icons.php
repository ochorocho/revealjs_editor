<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

$icon = static fn(string $file): array => [
    'provider' => SvgIconProvider::class,
    'source' => 'EXT:revealjs_editor/Resources/Public/Icons/' . $file,
];

return [
    'apps-pagetree-revealjs' => $icon('doktype-revealjs.svg'),
    'content-revealjs-slide-cover' => $icon('doktype-revealjs.svg'),
    'content-revealjs-slide-code' => $icon('doktype-revealjs.svg'),
];
