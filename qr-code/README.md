# Bongo QR Code Package

A high-performance, pure-PHP zero-dependency QR Code generator for Bongo PHP. Generates crisp, scalable vector SVGs, raster PNGs, Base64 Data URIs, and terminal ASCII art with preset builders for Wi-Fi, bKash, Nagad, emails, and geolocation.

## Features
- **Zero External Dependencies**: Pure PHP 8.0+ mathematics (GF(256) Reed-Solomon polynomial math).
- **Multiple Output Formats**: Scalable vector SVG, raster PNG (via GD), Base64 Data URI, and terminal ASCII.
- **Convenient Presets**: Easy builders for Wi-Fi configurations, bKash/Nagad scan-to-pay links, SMS, email, and coordinates.
- **Configurable Styling**: Dimensions, margin, foreground & background colors, and error correction levels (`L`, `M`, `Q`, `H`).

## Installation
```bash
php bongo package:add qr-code
```

## Basic Usage

```php
use Bongo\Packages\QrCode\QrCode;

// Generate SVG string
$svg = qr_code('https://bongo-framework.com')->size(300)->svg();

// Generate PNG binary
$png = qr_code('https://bongo-framework.com')->png();

// Generate Data URI for HTML <img> tag
$dataUri = qr_code('https://bongo-framework.com')->dataUri();
echo '<img src="' . $dataUri . '" alt="QR Code">';

// Custom colors and error correction
$custom = qr_code('https://example.com')
    ->color('#1e3a8a')
    ->backgroundColor('#f8fafc')
    ->margin(3)
    ->errorCorrection('H')
    ->svg();

// Save to disk
qr_code('https://example.com')->save('storage/app/receipt_qr.svg');
```

## Payment & Convenience Presets

```php
// bKash Merchant Payment
$bkashQr = QrCode::bKash('01700000000', amount: 500.0, reference: 'INV-1001')->svg();

// Nagad Merchant Payment
$nagadQr = QrCode::nagad('01800000000', amount: 750.0)->svg();

// Wi-Fi Connection
$wifiQr = QrCode::wifi('Office-Guest', 'SecurePass123', 'WPA')->svg();

// Email
$emailQr = QrCode::email('support@example.com', 'Help Needed', 'Hello support team...')->svg();
```

## CLI Command

```bash
# Print QR code directly to console terminal
php bongo qrcode:generate "https://bongo-framework.com"

# Save directly to a file
php bongo qrcode:generate "https://bongo-framework.com" --output=public/qr.png
```
