<?php

declare(strict_types=1);

namespace Bongo\Packages\Telegram;

use InvalidArgumentException;
use RuntimeException;

class Telegram
{
    protected string $botToken;
    protected string|int|null $defaultChatId;
    protected string $parseMode;
    protected bool $disableWebPagePreview;
    protected int $timeout;
    protected static ?array $mockResponse = null;

    public function __construct(?array $config = null)
    {
        $cfg = $config ?? (function_exists('config') ? config('telegram', []) : []);

        $this->botToken = (string) ($cfg['bot_token'] ?? env('TELEGRAM_BOT_TOKEN', ''));
        $this->defaultChatId = $cfg['chat_id'] ?? env('TELEGRAM_CHAT_ID', null);
        $this->parseMode = (string) ($cfg['parse_mode'] ?? 'HTML');
        $this->disableWebPagePreview = (bool) ($cfg['disable_web_page_preview'] ?? true);
        $this->timeout = (int) ($cfg['timeout'] ?? 15);
    }

    /**
     * Send a text message to a specific chat or the default chat.
     */
    public function sendMessage(string|int|null $chatId, string $text, array $options = []): array
    {
        $targetChat = $chatId ?? $this->defaultChatId;
        if (empty($targetChat)) {
            throw new InvalidArgumentException("Telegram chat ID is required.");
        }

        $params = array_merge([
            'chat_id'                  => $targetChat,
            'text'                     => $text,
            'parse_mode'               => $options['parse_mode'] ?? $this->parseMode,
            'disable_web_page_preview' => $options['disable_web_page_preview'] ?? $this->disableWebPagePreview,
        ], $options);

        return $this->request('sendMessage', $params);
    }

    /**
     * Convenience notification helper using default chat ID.
     */
    public function notify(string $text, array $options = []): array
    {
        return $this->sendMessage(null, $text, $options);
    }

    /**
     * Send a formatted alert message (e.g., info, warning, error, critical).
     */
    public function alert(string $title, string $message, string $level = 'error', ?array $extra = null): array
    {
        $emoji = match (strtolower($level)) {
            'info'     => 'ℹ️',
            'success'  => '✅',
            'warning'  => '⚠️',
            'critical' => '🚨',
            default    => '❌',
        };

        $appName = function_exists('config') ? config('app.name', 'Bongo App') : 'Bongo App';
        $appEnv = function_exists('env') ? env('APP_ENV', 'production') : 'production';
        $time = date('Y-m-d H:i:s T');

        // Every value is escaped: one stray "<" makes Telegram reject the whole message (lost alert)
        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = "{$emoji} <b>[" . $e(strtoupper($level)) . '] ' . $e($title) . "</b>\n";
        $html .= '<b>App:</b> ' . $e($appName) . ' (<code>' . $e($appEnv) . "</code>)\n";
        $html .= "<b>Time:</b> {$time}\n\n";
        $html .= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "\n";

        if (!empty($extra)) {
            $html .= "\n<b>Details:</b>\n<pre>" . htmlspecialchars(json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . "</pre>";
        }

        return $this->sendMessage(null, $html, ['parse_mode' => 'HTML']);
    }

    /**
     * Send a photo to a chat by URL or file path.
     */
    public function sendPhoto(string|int|null $chatId, string $photo, string $caption = '', array $options = []): array
    {
        $targetChat = $chatId ?? $this->defaultChatId;
        if (empty($targetChat)) {
            throw new InvalidArgumentException("Telegram chat ID is required.");
        }

        $params = array_merge([
            'chat_id'    => $targetChat,
            'photo'      => $photo,
            'caption'    => $caption,
            'parse_mode' => $options['parse_mode'] ?? $this->parseMode,
        ], $options);

        return $this->request('sendPhoto', $params);
    }

    /**
     * Test bot authentication and get bot user details.
     */
    public function getMe(): array
    {
        return $this->request('getMe');
    }

    /**
     * Set incoming webhook URL.
     */
    public function setWebhook(string $url): array
    {
        // Telegram then sends X-Telegram-Bot-Api-Secret-Token with every update: check it with verifyWebhook()
        $params = ['url' => $url];
        $secret = (string) (function_exists('config') ? config('telegram.webhook_secret', '') : '');
        if ($secret !== '') {
            $params['secret_token'] = $secret;
        }
        return $this->request('setWebhook', $params);
    }

    /**
     * True when an incoming update carries the secret given to setWebhook() (TELEGRAM_WEBHOOK_SECRET).
     * Without it anyone who learns the webhook URL can post fake updates.
     */
    public function verifyWebhook(array $headers = []): bool
    {
        $secret = (string) (function_exists('config') ? config('telegram.webhook_secret', '') : '');
        if ($secret === '') {
            return false;
        }
        $given = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'x-telegram-bot-api-secret-token') {
                $given = is_array($value) ? (string) reset($value) : (string) $value;
            }
        }
        return $given !== '' && hash_equals($secret, $given);
    }

    /**
     * Remove existing webhook.
     */
    public function deleteWebhook(): array
    {
        return $this->request('deleteWebhook');
    }

    /**
     * Set mock response for testing.
     */
    public static function fake(?array $response = null): void
    {
        self::$mockResponse = $response ?? [
            'ok'     => true,
            'result' => [
                'message_id' => 12345,
                'chat'       => ['id' => 999999, 'type' => 'private'],
                'date'       => time(),
                'text'       => 'Mock message',
            ],
        ];
    }

    public static function clearFake(): void
    {
        self::$mockResponse = null;
    }

    /**
     * Execute Telegram Bot API request.
     */
    protected function request(string $method, array $params = []): array
    {
        if (self::$mockResponse !== null) {
            return self::$mockResponse;
        }

        if (empty($this->botToken)) {
            throw new RuntimeException("Telegram Bot Token is missing. Set TELEGRAM_BOT_TOKEN in .env or config/telegram.php.");
        }

        $url = "https://api.telegram.org/bot{$this->botToken}/{$method}";

        $payload = json_encode($params);
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n" .
                             "Accept: application/json\r\n" .
                             "Content-Length: " . strlen((string) $payload) . "\r\n" .
                             "User-Agent: Bongo-Telegram/1.0\r\n",
                'content' => $payload,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RuntimeException("Could not connect to Telegram Bot API at {$method}.");
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Invalid response received from Telegram Bot API.");
        }

        if (empty($decoded['ok'])) {
            $error = $decoded['description'] ?? 'Unknown Telegram API error';
            throw new RuntimeException("Telegram API Error [{$method}]: {$error}");
        }

        return $decoded;
    }
}
