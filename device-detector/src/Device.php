<?php

declare(strict_types=1);

namespace Bongo\Packages\DeviceDetector;

use JsonSerializable;

class Device implements JsonSerializable
{
    protected string $userAgent;
    protected ?string $deviceType = null;
    protected ?string $browser = null;
    protected ?string $browserVersion = null;
    protected ?string $os = null;
    protected ?string $osVersion = null;
    protected ?string $botName = null;

    protected static array $bots = [
        'Googlebot' => 'Googlebot',
        'Googlebot-Image' => 'Googlebot-Image',
        'Bingbot' => 'bingbot',
        'Baiduspider' => 'Baiduspider',
        'YandexBot' => 'YandexBot',
        'DuckDuckBot' => 'DuckDuckBot',
        'Sogou' => 'Sogou',
        'Exabot' => 'Exabot',
        'faceBot' => 'facebookexternalhit',
        'Twitterbot' => 'Twitterbot',
        'TelegramBot' => 'TelegramBot',
        'WhatsApp' => 'WhatsApp',
        'Applebot' => 'Applebot',
        'ClaudeBot' => 'ClaudeBot',
        'GPTBot' => 'GPTBot',
        'ChatGPT-User' => 'ChatGPT-User',
        'AhrefsBot' => 'AhrefsBot',
        'SemrushBot' => 'SemrushBot',
        'MJ12bot' => 'MJ12bot',
        'DotBot' => 'DotBot',
        'UptimeRobot' => 'UptimeRobot',
        'Pingdom' => 'Pingdom',
    ];

    public function __construct(?string $userAgent = null)
    {
        $this->userAgent = $userAgent ?? static::detectUserAgent();
        $this->parse();
    }

    public static function detect(?string $userAgent = null): static
    {
        return new static($userAgent);
    }

    public static function detectUserAgent(): string
    {
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            return (string) $_SERVER['HTTP_USER_AGENT'];
        }

        if (function_exists('request') && request()) {
            return (string) request()->header('User-Agent', '');
        }

        return '';
    }

    public function userAgent(): string
    {
        return $this->userAgent;
    }

    public function isBot(): bool
    {
        return $this->botName !== null;
    }

    public function isRobot(): bool
    {
        return $this->isBot();
    }

    public function botName(): ?string
    {
        return $this->botName;
    }

    public function isMobile(): bool
    {
        return $this->deviceType === 'mobile';
    }

    public function isPhone(): bool
    {
        return $this->isMobile();
    }

    public function isTablet(): bool
    {
        return $this->deviceType === 'tablet';
    }

    public function isDesktop(): bool
    {
        return $this->deviceType === 'desktop';
    }

    public function deviceType(): string
    {
        return $this->deviceType ?? 'desktop';
    }

    public function browser(): string
    {
        return $this->browser ?? 'Unknown';
    }

    public function browserVersion(): string
    {
        return $this->browserVersion ?? 'Unknown';
    }

    public function isChrome(): bool
    {
        return $this->browser === 'Chrome';
    }

    public function isSafari(): bool
    {
        return $this->browser === 'Safari';
    }

    public function isFirefox(): bool
    {
        return $this->browser === 'Firefox';
    }

    public function isEdge(): bool
    {
        return $this->browser === 'Edge';
    }

    public function isOpera(): bool
    {
        return $this->browser === 'Opera';
    }

    public function os(): string
    {
        return $this->os ?? 'Unknown';
    }

    public function osVersion(): string
    {
        return $this->osVersion ?? 'Unknown';
    }

    public function isWindows(): bool
    {
        return $this->os === 'Windows';
    }

    public function isMac(): bool
    {
        return $this->os === 'macOS';
    }

    public function isIos(): bool
    {
        return $this->os === 'iOS';
    }

    public function isAndroid(): bool
    {
        return $this->os === 'Android';
    }

    public function isLinux(): bool
    {
        return $this->os === 'Linux';
    }

    public function toArray(): array
    {
        return [
            'userAgent' => $this->userAgent,
            'device' => $this->deviceType(),
            'isMobile' => $this->isMobile(),
            'isTablet' => $this->isTablet(),
            'isDesktop' => $this->isDesktop(),
            'isBot' => $this->isBot(),
            'botName' => $this->botName,
            'browser' => $this->browser(),
            'browserVersion' => $this->browserVersion(),
            'os' => $this->os(),
            'osVersion' => $this->osVersion(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    protected function parse(): void
    {
        $ua = $this->userAgent;
        if (trim($ua) === '') {
            $this->deviceType = 'desktop';
            $this->browser = 'Unknown';
            $this->os = 'Unknown';
            return;
        }

        // 1. Bot check
        foreach (static::$bots as $name => $sig) {
            if (stripos($ua, $sig) !== false) {
                $this->botName = $name;
                $this->deviceType = 'bot';
                break;
            }
        }

        if (function_exists('config')) {
            $custom = (array) config('device.custom_bots', []);
            foreach ($custom as $botSig) {
                if (stripos($ua, $botSig) !== false) {
                    $this->botName = $botSig;
                    $this->deviceType = 'bot';
                    break;
                }
            }
        }

        // 2. OS & Version
        $this->parseOs($ua);

        // 3. Browser & Version
        $this->parseBrowser($ua);

        // 4. Device Type
        if ($this->deviceType !== 'bot') {
            $this->parseDeviceType($ua);
        }
    }

    protected function parseOs(string $ua): void
    {
        if (preg_match('/Windows NT 10\.0/i', $ua)) {
            $this->os = 'Windows';
            $this->osVersion = '10/11';
        } elseif (preg_match('/Windows NT 6\.3/i', $ua)) {
            $this->os = 'Windows';
            $this->osVersion = '8.1';
        } elseif (preg_match('/Windows NT 6\.2/i', $ua)) {
            $this->os = 'Windows';
            $this->osVersion = '8';
        } elseif (preg_match('/Windows NT 6\.1/i', $ua)) {
            $this->os = 'Windows';
            $this->osVersion = '7';
        } elseif (preg_match('/Windows NT/i', $ua)) {
            $this->os = 'Windows';
            $this->osVersion = 'NT';
        } elseif (preg_match('/iPhone OS ([0-9_]+)/i', $ua, $m)) {
            $this->os = 'iOS';
            $this->osVersion = str_replace('_', '.', $m[1]);
        } elseif (preg_match('/iPad.*OS ([0-9_]+)/i', $ua, $m)) {
            $this->os = 'iOS';
            $this->osVersion = str_replace('_', '.', $m[1]);
        } elseif (preg_match('/Android[ -]?([0-9.]+)/i', $ua, $m)) {
            $this->os = 'Android';
            $this->osVersion = $m[1];
        } elseif (preg_match('/Mac OS X ([0-9_]+)/i', $ua, $m)) {
            $this->os = 'macOS';
            $this->osVersion = str_replace('_', '.', $m[1]);
        } elseif (stripos($ua, 'CrOS') !== false) {
            $this->os = 'Chrome OS';
            $this->osVersion = 'Unknown';
        } elseif (stripos($ua, 'Linux') !== false) {
            $this->os = 'Linux';
            $this->osVersion = 'Unknown';
        } else {
            $this->os = 'Unknown';
            $this->osVersion = 'Unknown';
        }
    }

    protected function parseBrowser(string $ua): void
    {
        if (preg_match('/Edg(?:e|A|iOS)?\/([0-9.]+)/i', $ua, $m)) {
            $this->browser = 'Edge';
            $this->browserVersion = $m[1];
        } elseif (preg_match('/OPR\/([0-9.]+)/i', $ua, $m) || preg_match('/Opera\/([0-9.]+)/i', $ua, $m)) {
            $this->browser = 'Opera';
            $this->browserVersion = $m[1];
        } elseif (preg_match('/SamsungBrowser\/([0-9.]+)/i', $ua, $m)) {
            $this->browser = 'Samsung Internet';
            $this->browserVersion = $m[1];
        } elseif (preg_match('/UCBrowser\/([0-9.]+)/i', $ua, $m)) {
            $this->browser = 'UC Browser';
            $this->browserVersion = $m[1];
        } elseif (preg_match('/Chrome\/([0-9.]+)/i', $ua, $m)) {
            $this->browser = 'Chrome';
            $this->browserVersion = $m[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/i', $ua, $m)) {
            $this->browser = 'Firefox';
            $this->browserVersion = $m[1];
        } elseif (preg_match('/Version\/([0-9.]+).*Safari/i', $ua, $m)) {
            $this->browser = 'Safari';
            $this->browserVersion = $m[1];
        } elseif (preg_match('/MSIE ([0-9.]+)/i', $ua, $m) || preg_match('/Trident\/.*rv:([0-9.]+)/i', $ua, $m)) {
            $this->browser = 'Internet Explorer';
            $this->browserVersion = $m[1];
        } else {
            $this->browser = 'Unknown';
            $this->browserVersion = 'Unknown';
        }
    }

    protected function parseDeviceType(string $ua): void
    {
        // Check tablet first
        if (
            stripos($ua, 'iPad') !== false ||
            stripos($ua, 'PlayBook') !== false ||
            stripos($ua, 'Tablet') !== false ||
            stripos($ua, 'Kindle') !== false ||
            (stripos($ua, 'Android') !== false && stripos($ua, 'Mobile') === false)
        ) {
            $this->deviceType = 'tablet';
            return;
        }

        // Check mobile
        if (
            stripos($ua, 'Mobi') !== false ||
            stripos($ua, 'iPhone') !== false ||
            stripos($ua, 'iPod') !== false ||
            stripos($ua, 'Windows Phone') !== false ||
            stripos($ua, 'BlackBerry') !== false
        ) {
            $this->deviceType = 'mobile';
            return;
        }

        $this->deviceType = 'desktop';
    }
}
