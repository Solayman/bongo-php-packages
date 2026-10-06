# Bongo Device Detector Package

A lightweight, zero-dependency User-Agent parser and client detector for Bongo PHP. Identifies device form factors (`mobile`, `tablet`, `desktop`, `bot`), operating systems, web browsers, and search engine crawlers with fluent inspection methods.

## Features
- **Zero External Dependencies**: Pure PHP 8.0+ regex parsing without massive third-party databases.
- **Form Factor Detection**: Accurately classifies smartphone vs tablet vs desktop vs bot.
- **Browser & OS Recognition**: Chrome, Safari, Edge, Firefox, Opera, Samsung Internet, UC Browser, Windows, macOS, iOS, Android, and Linux.
- **Search Engine & AI Bot Identification**: Detects Googlebot, Bingbot, Twitterbot, WhatsApp, Applebot, ClaudeBot, GPTBot, and custom crawlers.
- **Request Integration**: Seamlessly pulls from current HTTP request headers or accepts custom User-Agent strings.

## Installation
```bash
php bongo package:add device-detector
```

## Basic Usage

```php
use Bongo\Packages\DeviceDetector\Device;

// Auto-detect from current HTTP request
if (device()->isMobile()) {
    return view('mobile.home');
}

// Fluent inspections
device()->isDesktop(); // bool
device()->isTablet();  // bool
device()->isBot();     // bool

// Detailed details
device()->browser();        // "Chrome"
device()->browserVersion(); // "120.0.0.0"
device()->os();             // "Windows"
device()->osVersion();      // "10/11"

// Target specific environments
if (device()->isIos() || device()->isAndroid()) {
    // Show mobile app download banner
}
```

## Parse Explicit User-Agent

```php
$info = device('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1');

$info->isMobile(); // true
$info->os();       // "iOS"
$info->osVersion();// "17.0"
$info->browser();  // "Safari"
```

## CLI Inspection

```bash
php bongo device:detect "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36"
```
