<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Helpers.php';

use System\View\ViewCompiler;

// Template directives (@name(...)); the argument is null when the directive has no parentheses
if (class_exists(ViewCompiler::class)) {
    ViewCompiler::directive('bangla_number', function (?string $expression): string {
        $expression = trim((string) $expression);
        return "<?php echo htmlspecialchars(\Bongo\Packages\BanglaTools\Bangla::number({$expression}), ENT_QUOTES, 'UTF-8'); ?>";
    });

    ViewCompiler::directive('bangla_date', function (?string $expression): string {
        $expression = trim((string) $expression);
        $args = $expression !== '' ? $expression : 'null';
        return "<?php echo htmlspecialchars(\Bongo\Packages\BanglaTools\Bangla::date({$args}), ENT_QUOTES, 'UTF-8'); ?>";
    });

    ViewCompiler::directive('taka', function (?string $expression): string {
        $expression = trim((string) $expression);
        return "<?php echo htmlspecialchars(\Bongo\Packages\BanglaTools\Bangla::taka({$expression}), ENT_QUOTES, 'UTF-8'); ?>";
    });
}
