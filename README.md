# Bongo PHP Official Packages

Official, optional packages for the [Bongo PHP Framework](https://github.com/Solayman/bongo-php).

Each package is modular, self-contained, and follows Bongo's core principle of zero required third-party dependencies.

## Structure

```
bongo-php-packages/
├── index.json              # Catalog index: package versions, minimum core version, and sha256 checksums
├── sslcommerz/             # SSLCommerz payment gateway for Bangladesh
├── bkash/                  # bKash Tokenized Checkout payment gateway
├── sms/                    # Multi-driver SMS gateways + phone OTP
├── s3/                     # AWS S3 / Spaces / R2 / MinIO storage disk
├── social-login/           # OAuth 2.0 social login (Google, GitHub, Facebook)
├── nagad/                  # Nagad MFS payment gateway (RSA-2048 encryption)
├── stripe/                 # Stripe international payment gateway & webhooks
├── two-factor/             # RFC 6238 TOTP two-factor auth with pure-PHP SVG QR codes
├── image/                  # Pure-PHP image manipulation, cropping, WebP engine
├── backup/                 # Multi-database & asset zip backup engine
├── permission/             # Role-Based Access Control (RBAC) & permissions
├── zip/                    # Pure-PHP ZIP compression, extraction, streaming
├── ftp/                    # Zero-dependency FTP & FTPS filesystem storage driver
├── paypal/                 # PayPal REST API v2 Checkout payment gateway
├── api-tools/              # JsonResource transformers, OpenAPI 3.0 docs generator
├── settings/               # Dynamic database settings with type casting & caching
├── seo/                    # OpenGraph, Twitter Cards, JSON-LD, XML sitemaps
├── activity-log/           # Model activity & audit trail logger with attribute diffs
├── health/                 # Production uptime monitoring & HTTP /health check
└── file-manager/           # Web file manager & CodeMirror 5 embedded code editor
```

## Package Standards

All packages in this repository must comply with:
- **Zero Required Dependencies:** Pure PHP 8.0+, no Composer packages, no Node.js runtime.
- **PSR-12 Code Quality:** 0 errors checked via `php phpcs.phar`.
- **Full Test Coverage:** Unit and mock integration tests included in `tests/` (174 tests, 706 assertions).
- **Multi-Language Support:** All user-facing strings localized with `__()` (namespaced `package::key`), with `lang/en.json` required.
- **Security Defaults:** Server-side verification, strictly escaped output, secrets masked in logs.

## Installing in a Bongo Project

In your Bongo project:

```bash
php bongo add <package-name>
```

Or uncomment the package in `config/packages.php` and run:

```bash
php bongo package:install
```

## Running Package Tests

```bash
php run_tests.php
```

## License

Distributed under the [MIT License](LICENSE). Maintained by [Nobotech](https://nobotech.xyz).
