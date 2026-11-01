<?php

/**
 * Linden theme for webtrees. See LindenTheme.php.
 */

declare(strict_types=1);

namespace Linden\WebtreesTheme;

// A second copy of the theme (say, an old folder kept during an upgrade) would otherwise stop
// every page with "Cannot declare class"
if (!class_exists(LindenTheme::class, false)) {
    require __DIR__ . '/LindenTheme.php';
}

return new LindenTheme();
