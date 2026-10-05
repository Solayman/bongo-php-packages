<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Helpers.php';

use System\View\Template;

if (class_exists(Template::class) && method_exists(Template::class, 'directive')) {
    Template::directive('bangla_number', function (string $expression): string {
        return "<?php echo htmlspecialchars(\Bongo\Packages\BanglaTools\Bangla::number({$expression}), ENT_QUOTES, 'UTF-8'); ?>";
    });

    Template::directive('bangla_date', function (string $expression): string {
        $expression = trim($expression);
        $args = $expression !== '' ? $expression : 'null';
        return "<?php echo htmlspecialchars(\Bongo\Packages\BanglaTools\Bangla::date({$args}), ENT_QUOTES, 'UTF-8'); ?>";
    });

    Template::directive('taka', function (string $expression): string {
        return "<?php echo htmlspecialchars(\Bongo\Packages\BanglaTools\Bangla::taka({$expression}), ENT_QUOTES, 'UTF-8'); ?>";
    });
}
