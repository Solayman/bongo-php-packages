<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Helpers.php';

use System\View\ViewCompiler;

// Template directives (@name(...)); the argument is null when the directive has no parentheses
if (class_exists(ViewCompiler::class)) {
    ViewCompiler::directive('setting', function (?string $expression): string {
        $expression = trim((string) $expression);
        return "<?php echo htmlspecialchars((string) setting({$expression}), ENT_QUOTES, 'UTF-8'); ?>";
    });
}
