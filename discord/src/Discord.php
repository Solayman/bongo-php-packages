<?php

namespace Bongo\Packages\Discord;

class Discord
{
    protected static bool $faking = false;
    protected static array $sent = [];

    public static function fake(): void
    {
        static::$faking = true;
        static::$sent = [];
    }

    public static function isFaking(): bool
    {
        return static::$faking;
    }

    public static function getSent(): array
    {
        return static::$sent;
    }

    public static function send(string $content, ?string $webhookUrl = null, array $options = []): array
    {
        $url = $webhookUrl ?? (string) config('discord.webhook_url', '');
        if ($url === '' && !static::$faking) {
            throw new \InvalidArgumentException('Discord webhook URL is not configured.');
        }

        $payload = array_merge([
            'content'  => $content,
            'username' => config('discord.username', 'Bongo Bot'),
        ], $options);

        if (!empty(config('discord.avatar_url'))) {
            $payload['avatar_url'] = config('discord.avatar_url');
        }

        if (static::$faking) {
            static::$sent[] = ['url' => $url, 'payload' => $payload];
            return ['success' => true, 'status_code' => 204, 'body' => ''];
        }

        return static::postJson($url, $payload);
    }

    public static function alert(string $title, string $message, string $level = 'info', array $fields = [], ?string $webhookUrl = null): array
    {
        $colors = [
            'info'     => 0x3b82f6, // Blue
            'success'  => 0x22c55e, // Green
            'warning'  => 0xf59e0b, // Amber
            'error'    => 0xef4444, // Red
            'critical' => 0x991b1b, // Dark Red
        ];

        $emojis = [
            'info'     => 'ℹ️',
            'success'  => '✅',
            'warning'  => '⚠️',
            'error'    => '🚨',
            'critical' => '🔥',
        ];

        $color = $colors[$level] ?? $colors['info'];
        $emoji = $emojis[$level] ?? '📢';

        $embed = DiscordEmbed::make("{$emoji} {$title}", $message, $color)
            ->footer('Bongo Alert Engine')
            ->timestamp();

        foreach ($fields as $name => $value) {
            $embed->addField((string) $name, (string) $value, true);
        }

        return static::send('', $webhookUrl, [
            'embeds' => [$embed->toArray()],
        ]);
    }

    public static function embed(string $title = '', string $description = '', int $color = 0x3b82f6): DiscordEmbed
    {
        return DiscordEmbed::make($title, $description, $color);
    }

    protected static function postJson(string $url, array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'User-Agent: Bongo-Discord/1.0',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => (int) config('discord.timeout', 5),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch) ?: null;
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $success = ($status === 200 || $status === 204) && $error === null;

        return [
            'success'     => $success,
            'status_code' => $status,
            'body'        => $body !== false ? $body : '',
            'error'       => $error,
        ];
    }
}
