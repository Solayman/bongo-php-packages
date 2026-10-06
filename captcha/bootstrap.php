<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Helpers.php';

use System\View\ViewCompiler;

// Template directives (@name(...)); the argument is null when the directive has no parentheses
if (class_exists(ViewCompiler::class)) {
    ViewCompiler::directive('captcha', function (?string $expression): string {
        $expression = trim((string) $expression);
        $args = $expression !== '' ? $expression : '[]';
        return "<?php echo \Bongo\Packages\Captcha\Captcha::render({$args}); ?>";
    });

    ViewCompiler::directive('captchascript', function (?string $expression): string {
        $expression = trim((string) $expression);
        $driver = $expression !== '' ? $expression : 'null';
        return "<?php echo \Bongo\Packages\Captcha\Captcha::script({$driver}); ?>";
    });
}
