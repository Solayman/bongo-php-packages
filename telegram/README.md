# Bongo Telegram Notifications & Alerts (`telegram`)

[![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg)](#testing)
[![PHP](https://img.shields.io/badge/php-8.0%2B-blue.svg)](https://php.net)
[![Dependencies](https://img.shields.io/badge/dependencies-0%20required-orange.svg)](#zero-dependencies)

Real-time Telegram Bot notifications, alerts, and messaging package for the **Bongo PHP Framework**. Zero external dependencies, pure PHP 8.0+.

---

## 🚀 Features

- **Zero Third-Party Dependencies:** Pure PHP 8.0+ implementation using native streams/cURL.
- **Rich Alerts & Formatting:** Send HTML and Markdown notifications with emojis, timestamps, environment badges, and JSON details.
- **Media Support:** Send photos, documents, and messages.
- **Mocking for Testing:** `Telegram::fake()` allows testing without sending live network requests.
- **CLI Utilities:**
  - `php bongo telegram:send "Hello from CLI"`
  - `php bongo telegram:test` (tests bot token authentication and sends a ping)
- **Global Helpers:** `telegram_notify()` and `telegram_alert()`.

---

## 📦 Installation

```bash
php bongo add telegram
```

---

## ⚙️ Configuration (`config/telegram.php`)

```php
return [
    'bot_token'                => env('TELEGRAM_BOT_TOKEN', ''),
    'chat_id'                  => env('TELEGRAM_CHAT_ID', ''),
    'parse_mode'               => env('TELEGRAM_PARSE_MODE', 'HTML'),
    'disable_web_page_preview' => (bool) env('TELEGRAM_DISABLE_PREVIEW', true),
    'timeout'                  => 15,
];
```

---

## 💡 Usage

### Basic Messages & Notifications

```php
use Bongo\Packages\Telegram\Telegram;

$telegram = new Telegram();

// Send to default chat
$telegram->notify('🚀 New customer registered!');

// Send to specific chat or channel (@mychannel)
$telegram->sendMessage('@mychannel', 'Weekly report is ready.');
```

### Formatted Alerts

```php
use Bongo\Packages\Telegram\Telegram;

$telegram = new Telegram();

$telegram->alert(
    title: 'High Server Memory Usage',
    message: 'Memory usage exceeded 90% threshold.',
    level: 'critical',
    extra: ['memory' => '94%', 'pid' => 1420]
);
```

### Using Global Helpers

```php
telegram_notify('Payment confirmed for order #1084');
telegram_alert('Queue Job Failed', 'SendWelcomeEmail timed out', 'error');
```
