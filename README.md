# Bongo PHP Official Packages

Official, optional packages for the [Bongo PHP Framework](https://github.com/Solayman/bongo-php).

Each package is modular, self-contained, and follows Bongo's core principle of zero required third-party dependencies.

## Structure

```
bongo-php-packages/
├── index.json              # Catalog index: package versions, minimum core version, and sha256 checksums
├── sslcommerz/             # SSLCommerz payment gateway
├── bkash/                  # bKash payment gateway
├── sms/                    # Bangladeshi SMS gateways + OTP
├── s3/                     # S3 / Spaces / R2 storage disk
└── social-login/           # OAuth login (Google, Facebook, GitHub)
```

## Package Standards

All packages in this repository must comply with:
- **Zero Required Dependencies:** Pure PHP 8.0+, no Composer packages, no Node.js runtime.
- **PSR-12 Code Quality:** 0 errors checked via `php phpcs.phar`.
- **Full Test Coverage:** Unit and mock integration tests included in `tests/`.
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

## License

Distributed under the [MIT License](LICENSE). Maintained by [Nobotech](https://nobotech.xyz).
