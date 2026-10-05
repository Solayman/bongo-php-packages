<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Token
    |--------------------------------------------------------------------------
    |
    | Create a bot via @BotFather on Telegram to obtain your unique token.
    | Example: "123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ"
    |
    */
    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Default Target Chat ID
    |--------------------------------------------------------------------------
    |
    | The default chat ID, group ID, or channel (@channelname) to which
    | notifications and alerts will be dispatched when no chat ID is given.
    |
    */
    'chat_id' => env('TELEGRAM_CHAT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Default Parse Mode
    |--------------------------------------------------------------------------
    |
    | Supported values: "HTML", "Markdown", "MarkdownV2"
    |
    */
    'parse_mode' => env('TELEGRAM_PARSE_MODE', 'HTML'),

    /*
    |--------------------------------------------------------------------------
    | Disable Web Page Preview
    |--------------------------------------------------------------------------
    |
    | When true, link previews will not be generated in messages.
    |
    */
    'disable_web_page_preview' => (bool) env('TELEGRAM_DISABLE_PREVIEW', true),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | HTTP connection and request timeout in seconds.
    |
    */
    'timeout' => 15,
];
