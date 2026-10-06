# Bongo AamarPay Payment Gateway (`aamarpay`)

[![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg)](#testing)
[![PHP](https://img.shields.io/badge/php-8.0%2B-blue.svg)](https://php.net)
[![Dependencies](https://img.shields.io/badge/dependencies-0%20required-orange.svg)](#zero-dependencies)

Official AamarPay payment gateway integration for the **Bongo PHP Framework**. Zero external dependencies. Provides complete coverage for Bangladeshi mobile financial services (bKash, Nagad, Rocket, Upay), cards (Visa, MasterCard, Amex), and internet banking.

---

## 🚀 Features

- **Pure PHP 8.0+ Integration:** Zero Composer packages or third-party wrappers.
- **Dual Environment Switching:** Sandbox and Live production endpoints configurable via `.env`.
- **Payment Verification:** Instant server-to-server transaction validation with `AamarPay::verify($tranId)`.
- **Pre-Built UI:** Ready-to-use views for checkout, success, failure, and cancellation.
- **Audit Logging:** Optional database logging in `aamarpay_transactions` table.
- **CLI Health Check:** `php bongo aamarpay:check` command to verify merchant configuration and gateway connectivity.

---

## 📦 Installation

```bash
php bongo add aamarpay
```

Run migration to create transaction log table:
```bash
php bongo migrate
```

---

## ⚙️ Configuration (`config/aamarpay.php`)

```php
return [
    'store_id'      => env('AAMARPAY_STORE_ID', 'aamarpaytest'),
    'signature_key' => env('AAMARPAY_SIGNATURE_KEY', '28c78bb1f45112f5a40b956f9d102abb'),
    'sandbox'       => (bool) env('AAMARPAY_SANDBOX', true),
    'currency'      => env('AAMARPAY_CURRENCY', 'BDT'),
    'success_url'   => '/aamarpay/success',
    'fail_url'      => '/aamarpay/fail',
    'cancel_url'    => '/aamarpay/cancel',
];
```

---

## 💡 Usage

### Initiating a Payment

```php
use Bongo\Packages\Aamarpay\AamarPay;

$gateway = new AamarPay();

$response = $gateway->initiate([
    'amount'           => 1250.00,
    'tran_id'          => 'ORD_' . time(),
    'customer_name'    => 'John Doe',
    'customer_email'   => 'john@example.com',
    'customer_phone'   => '01711000000',
    'customer_address' => 'Gulshan-2, Dhaka',
]);

// Redirect customer to gateway payment URL:
header('Location: ' . $response['payment_url']);
exit;
```

### Verifying a Transaction

```php
$gateway = new AamarPay();
$result = $gateway->verify($tranId);

if ($result['success']) {
    // Payment verified and completed
    $paidAmount = $result['amount'];
    $bankTranId = $result['bank_tran_id'];
}
```

## Verification and events

Never decide from the posted callback data: `verify($tranId)` (and `isSuccess($post)`, which now calls it) asks AamarPay's trxcheck API. The paid amount and currency must match the transaction stored by `initiate()` (otherwise `AMOUNT_MISMATCH`). A completed payment is never changed back to failed, and `payment.success` / `payment.failed` (with `gateway` = `aamarpay`) fire once per stored transaction.
