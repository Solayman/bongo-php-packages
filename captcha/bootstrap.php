<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Helpers.php';

use System\View\Template;

if (class_exists(Template::class) && method_exists(Template::class, 'directive')) {
    Template::directive('captcha', function (string $expression): string {
        $expression = trim($expression);
        $args = $expression !== '' ? $expression : '[]';
        return "<?php echo \Bongo\Packages\Captcha\Captcha::render({$args}); ?>";
    });

    Template::directive('captchascript', function (string $expression): string {
        $expression = trim($expression);
        $driver = $expression !== '' ? $expression : 'null';
        return "<?php echo \Bongo\Packages\Captcha\Captcha::script({$driver}); ?>";
    });
}
