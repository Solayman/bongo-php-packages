<?php

declare(strict_types=1);

use Bongo\Packages\Telegram\Telegram;

if (!function_exists('telegram_notify')) {
    function telegram_notify(string $message, array $options = []): array
    {
        return (new Telegram())->notify($message, $options);
    }
}

if (!function_exists('telegram_alert')) {
    function telegram_alert(string $title, string $message, string $level = 'error', ?array $extra = null): array
    {
        return (new Telegram())->alert($title, $message, $level, $extra);
    }
}
