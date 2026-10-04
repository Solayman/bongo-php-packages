<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Helpers.php';

use System\View\Template;

if (class_exists(Template::class) && method_exists(Template::class, 'directive')) {
    Template::directive('setting', function (string $expression): string {
        return "<?php echo htmlspecialchars((string) setting({$expression}), ENT_QUOTES, 'UTF-8'); ?>";
    });
}
