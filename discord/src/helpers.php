<?php

use Bongo\Packages\Discord\Discord;

if (!function_exists('discord_notify')) {
    function discord_notify(string $message, ?string $webhookUrl = null): array
    {
        return Discord::send($message, $webhookUrl);
    }
}

if (!function_exists('discord_alert')) {
    function discord_alert(string $title, string $message, string $level = 'info', array $fields = [], ?string $webhookUrl = null): array
    {
        return Discord::alert($title, $message, $level, $fields, $webhookUrl);
    }
}
