<?php

declare(strict_types=1);

require_once __DIR__ . '/../BongoMvc/config/bootstrap.php';

// Autoload packages from this repository
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Bongo\\Packages\\')) {
        $relative = substr($class, strlen('Bongo\\Packages\\'));
        $parts = explode('\\', $relative);
        $raw = $parts[0];
        // Convert PascalCase to kebab-case (e.g. SocialLogin -> social-login)
        $kebab = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $raw));
        $lower = strtolower($raw);
        $pkgDir = is_dir(__DIR__ . '/' . $kebab) ? $kebab : $lower;

        $subPath = implode('/', array_slice($parts, 1));
        $file = __DIR__ . '/' . $pkgDir . '/src/' . $subPath . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
}, true, true);


$target = $argv[1] ?? 'all';
$exitCode = 0;

if ($target === 'all') {
    $dirs = array_filter(glob(__DIR__ . '/*'), 'is_dir');
    $php = PHP_BINARY;
    $script = __FILE__;
    foreach ($dirs as $pkgDir) {
        $pkgName = basename($pkgDir);
        $testsDir = $pkgDir . '/tests';
        if (is_dir($testsDir)) {
            passthru(escapeshellcmd($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($pkgName), $res);
            if ($res !== 0) {
                $exitCode = $res;
            }
        }
    }
} else {
    $testsDir = __DIR__ . '/' . $target . '/tests';
    if (!is_dir($testsDir)) {
        echo "Tests directory not found: $testsDir\n";
        exit(1);
    }
    echo "\n=== Testing package: $target ===\n";
    $exitCode = System\Testing\Runner::run($testsDir);
}

exit($exitCode);
