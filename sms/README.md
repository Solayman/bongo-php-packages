# Bongo SMS & OTP Authentication



<p align="center">
  <img src="docs/screenshot.svg" alt="sms screenshot" width="800">
</p>
Official zero-dependency SMS gateway and phone OTP authentication package for the Bongo PHP framework.

Supports popular Bangladeshi SMS gateways (BulkSMSBD, SSL Wireless, Alpha SMS) and local `log` driver with a clean, extensible driver architecture.

---

## 🚀 3-Step Quick Start

### 1. Add Package
```bash
php bongo add sms
```

### 2. Configure Environment (`.env`)
Choose your gateway driver:
```env
# Default driver: "log", "bulksmsbd", "sslwireless", "alphasms"
SMS_DRIVER=log

# BulkSMSBD credentials:
BULKSMSBD_API_KEY=your_api_key
BULKSMSBD_SENDER_ID=your_sender_id

# SSL Wireless credentials:
SSLWIRELESS_API_TOKEN=your_token
SSLWIRELESS_SID=your_sid

# Alpha SMS credentials:
ALPHASMS_API_KEY=your_api_key
ALPHASMS_SENDER_ID=your_sender_id
```

### 3. Run Migrations (for OTP)
```bash
php bongo migrate
```

---

## 💻 Sending SMS Messages

### Quick SMS Dispatch
```php
use Bongo\Packages\Sms\SMS;

// Send using default driver configured in .env
$result = SMS::send('01700000000', 'Your order #1042 has shipped!');

if ($result['success']) {
    echo "Sent! ID: " . $result['id'];
} else {
    echo "Error: " . $result['error'];
}

// Or send with a specific driver on the fly:
SMS::send('01800000000', 'Alert message', 'sslwireless');
```

---

## 🔐 Phone OTP Authentication

The package includes complete, out-of-the-box OTP generation, resend cooldown throttling, and verification.

### Ready-to-Use Routes
The package registers:
- `GET  /otp/login`: Mobile number entry form
- `POST /otp/send`: Generates and sends OTP code via SMS
- `GET  /otp/verify`: 6-digit code verification form with countdown resend
- `POST /otp/verify`: Verifies code and authenticates the user

### Programmatic OTP API
```php
use Bongo\Packages\Sms\OTP;

// 1. Send OTP code:
try {
    $res = OTP::send('01700000000', 'login');
    echo "OTP sent. Expires at: " . $res['expires_at'];
} catch (\RuntimeException $e) {
    echo "Cooldown active: " . $e->getMessage();
}

// 2. Verify code:
$isValid = OTP::verify('01700000000', $submittedCode, 'login');

if ($isValid) {
    // Code verified! Authenticate user or proceed
}
```

---

## 🎨 Customizing Views & Translations

```bash
# Publish views to resources/views/vendor/sms/
php bongo package:publish sms --views

# Publish translations to resources/lang/vendor/sms/
php bongo package:publish sms --lang

# Publish config to config/sms.php
php bongo package:publish sms --config
```

---

## 🛠️ CLI Diagnostics & Tools

Send a test SMS directly from the terminal:
```bash
php bongo sms:send 01700000000 "Test message"
```

Verify your SMS gateway settings and driver connectivity:
```bash
php bongo sms:check
```

Run the package test suite:
```bash
php bongo package:test sms
```
