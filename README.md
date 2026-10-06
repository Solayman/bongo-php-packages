# Bongo PHP Official Packages Suite

[![Tests](https://img.shields.io/badge/tests-232%20passed%20(980%20assertions)-brightgreen.svg)](#running-package-tests)
[![PHP](https://img.shields.io/badge/php-8.0%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Dependencies](https://img.shields.io/badge/dependencies-0%20required-orange.svg)](#core-principles)

Official, production-ready package suite for the [Bongo PHP Framework](https://github.com/Solayman/bongo-php).

Every package is modular, self-contained, and follows Bongo's core principle of **zero required third-party dependencies** (no mandatory Composer packages, no Node.js runtime, no asset build step). Built with pure PHP 8.0+, fully PSR-12 compliant, and tested with **232 automated unit and integration tests (980 assertions)** across 29 official packages.

---

## 📦 Package Catalog Overview

| Package | Category | Version | Description | Quick Add |
|---|---|---|---|---|
| [`sslcommerz`](#1-sslcommerz-v100) | Payments | 1.0.0 | SSLCommerz payment gateway for Bangladesh with token validation, sandbox/live modes, IPN handling, and CLI verification | `php bongo add sslcommerz` |
| [`bkash`](#2-bkash-v100) | Payments | 1.0.0 | bKash Tokenized Checkout payment gateway with token caching, payment execution, query, refund, and CLI verification | `php bongo add bkash` |
| [`nagad`](#3-nagad-v100) | Payments | 1.0.0 | Nagad MFS payment gateway with native OpenSSL RSA-2048 encryption, two-step checkout, and CLI key generator | `php bongo add nagad` |
| [`aamarpay`](#22-aamarpay-v100) | Payments | 1.0.0 | AamarPay payment gateway for Bangladesh with sandbox/live modes, MFS support (bKash, Nagad, Rocket), and TrxCheck verification | `php bongo add aamarpay` |
| [`stripe`](#4-stripe-v100) | Payments | 1.0.0 | Stripe international payment gateway with Checkout Sessions, Payment Intents, refunds, and HMAC-SHA256 webhooks | `php bongo add stripe` |
| [`paypal`](#5-paypal-v100) | Payments | 1.0.0 | PayPal REST API v2 Checkout payment gateway with payment capture, authorization holds, refunds, and verified webhooks | `php bongo add paypal` |
| [`two-factor`](#6-two-factor-v100) | Security | 1.0.0 | RFC 6238 TOTP two-factor authentication with pure-PHP SVG QR codes, hashed backup recovery codes, and middleware | `php bongo add two-factor` |
| [`captcha`](#21-captcha-v100) | Security | 1.0.0 | Universal anti-bot verification suite (Cloudflare Turnstile, Google reCAPTCHA v2/v3, hCaptcha, pure-PHP math & GD image challenges) | `php bongo add captcha` |
| [`social-login`](#7-social-login-v100) | Security | 1.0.0 | OAuth 2.0 social authentication for Google, GitHub, and Facebook with CSRF protection and private email resolution | `php bongo add social-login` |
| [`permission`](#8-permission-v100) | Security | 1.0.0 | Role-Based Access Control (RBAC) and permissions management with `HasRoles` trait, super-admin bypass, and CLI tools | `php bongo add permission` |
| [`impersonate`](#27-impersonate-v100) | Security | 1.0.0 | Zero-dependency user impersonation for admins with secure session swapping and floating leave bar | `php bongo add impersonate` |
| [`sms`](#9-sms-v100) | Communications | 1.0.0 | Multi-driver SMS gateway (BulkSMSBD, SSL Wireless, Alpha SMS) with 6-digit phone OTP generation and throttling | `php bongo add sms` |
| [`telegram`](#23-telegram-v100) | Communications | 1.0.0 | Real-time Telegram Bot notifications, rich system alerts, channel broadcasts, and media messaging with pure-PHP Bot API | `php bongo add telegram` |
| [`discord`](#29-discord-v100) | Communications | 1.0.0 | Zero-dependency Discord webhook alerts, notifications, and rich embed builder | `php bongo add discord` |
| [`bangla-tools`](#24-bangla-tools-v100) | Localization | 1.0.0 | Bengali linguistic toolkit (Bangla numerals, number-to-words, South Asian Taka currency, Bongabdo calendar, and Unicode normalizer) | `php bongo add bangla-tools` |
| [`bd-locations`](#25-bd-locations-v100) | Localization | 1.0.0 | Complete Bangladesh administrative divisions, 64 districts, upazilas, postcodes, and shipping zones in English and Bengali | `php bongo add bd-locations` |
| [`s3`](#10-s3-v100) | Storage | 1.0.0 | AWS SigV4 object storage disk for Amazon S3, DigitalOcean Spaces, Cloudflare R2, and MinIO with presigned temporary URLs | `php bongo add s3` |
| [`ftp`](#11-ftp-v100) | Storage | 1.0.0 | Zero-dependency FTP and FTPS filesystem driver extending Bongo's `Storage` facade with pure-PHP socket transport | `php bongo add ftp` |
| [`file-manager`](#12-file-manager-v100) | Storage & UI | 1.0.0 | Zero-dependency web file manager, media lightbox, WYSIWYG picker, and embedded CodeMirror 5 code editor | `php bongo add file-manager` |
| [`image`](#13-image-v100) | Media | 1.0.0 | Pure-PHP image manipulation, smart cropping, WebP compression, watermarking with alpha blending, and filters | `php bongo add image` |
| [`zip`](#14-zip-v100) | Archiving | 1.0.0 | Pure-PHP ZIP compression, extraction, streaming downloads, and Zip-Slip path traversal protection | `php bongo add zip` |
| [`backup`](#15-backup-v100) | Operations | 1.0.0 | Zero-dependency multi-database (SQLite, MySQL, PostgreSQL) and application asset backup engine with multi-disk routing | `php bongo add backup` |
| [`queue-monitor`](#28-queue-monitor-v100) | Operations | 1.0.0 | Zero-dependency web monitor dashboard for Bongo background queue jobs, pending counts, and failed job retries | `php bongo add queue-monitor` |
| [`webhooks`](#26-webhooks-v100) | Developer Tools | 1.0.0 | Zero-dependency outbound webhook dispatcher with HMAC-SHA256 signing, delivery logging, and inbound verification | `php bongo add webhooks` |
| [`api-tools`](#16-api-tools-v100) | Developer Tools | 1.0.0 | API development suite with `JsonResource` transformers, OpenAPI 3.0 docs generator, and sliding window rate limiter | `php bongo add api-tools` |
| [`settings`](#17-settings-v100) | Application | 1.0.0 | Dynamic application & store settings in database with type casting, caching, `setting()` helper, and CLI suite | `php bongo add settings` |
| [`seo`](#18-seo-v100) | Marketing | 1.0.0 | SEO metadata suite with OpenGraph, Twitter Cards, Schema.org JSON-LD structured data, and dynamic XML sitemaps | `php bongo add seo` |
| [`activity-log`](#19-activity-log-v100) | Auditing | 1.0.0 | Enterprise audit trail and model activity logger with old vs new diff tracking, client telemetry, and retention cleanup | `php bongo add activity-log` |
| [`health`](#20-health-v100) | Monitoring | 1.0.0 | Production uptime monitoring and health check suite for Database, Storage, Cache, Redis, and Disk Space with JSON endpoint | `php bongo add health` |

---

## 🔍 Detailed Package Descriptions

### 1. `sslcommerz` (v1.0.0)
**SSLCommerz Payment Gateway for Bangladesh**
- **Problem Solved:** Bangladeshi e-commerce and SaaS platforms require seamless, regulated domestic payment processing across local debit/credit cards, mobile wallets (bKash, Nagad, Rocket), and internet banking.
- **Architecture & Highlights:**
  - Complete zero-dependency implementation of the SSLCommerz EasyCheckout and Hosted Redirect APIs.
  - Automated cryptographic transaction validation via server-to-server IPN (Instant Payment Notification) and session validation endpoint.
  - Dual environment switching (`sandbox` vs `live`) via `.env` configuration.
  - Built-in transaction logging, refund processing, and localized English and Bengali customer feedback templates.
  - Pre-built checkout partials and status redirection handlers (`success`, `fail`, `cancel`, `ipn`).
- **CLI Utilities:** `php bongo sslcommerz:check` (validates merchant credentials, API connectivity, and endpoint health).
- **Install:** `php bongo add sslcommerz`

---

### 2. `bkash` (v1.0.0)
**bKash Tokenized Checkout Payment Gateway**
- **Problem Solved:** bKash is the predominant Mobile Financial Service in Bangladesh. Integrating Tokenized Checkout requires dynamic grant token management, agreement registration, and execution workflows without Composer packages.
- **Architecture & Highlights:**
  - Implements bKash Tokenized Checkout API v1.2.0-beta with automated token renewal and caching.
  - Supports one-time payments, authorization holds, agreement-based recurring checkouts, and refund requests.
  - Resilient HTTP communication using Bongo's native cURL client with automated retry and SSL certificate pinning support.
  - Comprehensive database migration tracking bKash transaction IDs (`trxID`), payment IDs, agreement IDs, and customer phone numbers.
- **CLI Utilities:** `php bongo bkash:check` (probes bKash auth servers and checks token caching status).
- **Install:** `php bongo add bkash`

---

### 3. `nagad` (v1.0.0)
**Nagad Mobile Financial Service (MFS) Payment Gateway**
- **Problem Solved:** Nagad uses complex RSA-2048 asymmetric encryption, SHA256withRSA digital signatures, and AES-128-CBC payload sealing that typically force developers to install bulky third-party libraries.
- **Architecture & Highlights:**
  - Pure-PHP implementation leveraging PHP's native `ext-openssl` for RSA key generation, message signing, and payload encryption.
  - Two-step initialization flow: generates cryptographic client timestamps, challenges, and public-key encrypted request payloads.
  - Verifies payment status directly against Nagad PGW v-0.2 servers with digital signature authenticity checks.
  - Built-in RSA keypair generator producing production-grade PEM keys directly in `storage/nagad/`.
- **CLI Utilities:**
  - `php bongo nagad:keys --save`: Generates a 2048-bit RSA private and public keypair and updates `.env`.
  - `php bongo nagad:check`: Verifies RSA key configurations and sandbox/live handshake.
- **Install:** `php bongo add nagad`

---

### 4. `stripe` (v1.0.0)
**Stripe International Payment Gateway & Webhooks**
- **Problem Solved:** International credit card processing, Apple Pay, Google Pay, and subscription billing without bloating the vendor directory with Stripe's 30MB official PHP SDK.
- **Architecture & Highlights:**
  - Lightweight, direct integration with Stripe REST API (2023-10-16+ API specification).
  - Stripe Checkout Sessions generator with customer pre-fill, line item metadata, shipping options, and automatic tax calculations.
  - Direct Payment Intents API support for embedded custom card forms.
  - Cryptographic HMAC-SHA256 webhook signature verification (`Stripe-Signature` header) preventing replay and timing attacks.
  - Full and partial refund management directly from controllers.
- **CLI Utilities:** `php bongo stripe:check` (tests Stripe secret keys and verifies account balance and currency capabilities).
- **Install:** `php bongo add stripe`

---

### 5. `paypal` (v1.0.0)
**PayPal REST API v2 Checkout Gateway**
- **Problem Solved:** Global PayPal payment processing, hosted buyer approvals, and recurring subscriptions without legacy SDK dependencies.
- **Architecture & Highlights:**
  - Implements PayPal Orders v2 REST API with automatic OAuth 2.0 client credential bearer token management.
  - End-to-end checkout flow: order creation (`capture` or `authorize`), redirect approval, and post-approval order capture.
  - Webhook validation using PayPal's cryptographic signature verification algorithm (RSA-SHA256 with cert URL validation).
  - Comprehensive refund and void capabilities with detailed audit trails in database migrations.
- **CLI Utilities:** `php bongo paypal:check` (authenticates OAuth credentials against PayPal sandbox or live servers).
- **Install:** `php bongo add paypal`

---

### 6. `two-factor` (v1.0.0)
**RFC 6238 TOTP Two-Factor Authentication Subsystem**
- **Problem Solved:** Modern security standards require multi-factor authentication (MFA/2FA), but traditional solutions rely on third-party QR code generation APIs (exposing user secrets to Google Charts) or heavy GD-based libraries.
- **Architecture & Highlights:**
  - RFC 6238 compliant Time-Based One-Time Password (TOTP) algorithm using pure PHP `hash_hmac('sha1')`.
  - Built-in pure-PHP SVG QR code matrix generator: implements Galois Field $GF(2^8)$ arithmetic, Reed-Solomon polynomial error correction, and vector SVG output with zero external API calls or image libraries.
  - Configurable drift window (tolerating $\pm 1$ time steps to handle mobile clock skew).
  - Single-use hashed emergency backup recovery codes generated with cryptographically secure pseudo-random bytes.
  - Includes `TwoFactorMiddleware`, setup wizard views, confirmation challenges, and user model integration.
- **CLI Utilities:** `php bongo 2fa:generate <user_id>` (generates emergency 2FA setup codes and recovery keys from the terminal).
- **Install:** `php bongo add two-factor`

---

### 7. `social-login` (v1.0.0)
**OAuth 2.0 Social Authentication (Google, GitHub, Facebook)**
- **Problem Solved:** Frictionless user onboarding via third-party social providers without installing large OAuth client libraries or Socialite.
- **Architecture & Highlights:**
  - Pure-PHP OAuth 2.0 authorization code flow implementation with automatic cryptographic CSRF state token verification.
  - Supported providers: Google (OpenID Connect / OAuth 2.0), GitHub (OAuth 2.0 with private email resolution via `/user/emails`), and Facebook (Graph API v18.0).
  - Automatically matches existing accounts by verified email or provisions new user models with secure randomized passwords.
  - Extensible provider registry allowing custom OAuth 2.0 identity providers to be registered via runtime callbacks.
  - Pre-styled SVG brand button partials and responsive login dialogs with full English and Bengali localization.
- **Install:** `php bongo add social-login`

---

### 8. `permission` (v1.0.0)
**Role-Based Access Control (RBAC) & Permissions Engine**
- **Problem Solved:** Fine-grained access management, role hierarchies, and permission enforcement for enterprise SaaS, multi-tenant applications, and admin panels.
- **Architecture & Highlights:**
  - Clean database schema: `roles`, `permissions`, `role_permissions`, and `user_roles` with batched eager loading to eliminate N+1 queries.
  - `HasRoles` trait: provides `$user->hasRole('admin')`, `$user->can('posts.create')`, `$user->assignRole()`, and `$user->givePermission()`.
  - Super-Admin bypass: configurable super-administrator role that automatically passes all permission checks.
  - HTTP middleware: `RoleMiddleware` and `PermissionMiddleware` with redirect or JSON 403 Forbidden handling.
  - Template directives: `@role('admin')` and `@can('edit')` helpers for conditional view rendering.
- **CLI Utilities:**
  - `php bongo role:create <name>`: Creates a new system role.
  - `php bongo permission:create <name>`: Registers a new system permission.
  - `php bongo role:assign <user_id> <role>`: Assigns a role to a user.
- **Install:** `php bongo add permission`

---

### 9. `sms` (v1.0.0)
**Multi-Driver SMS Gateway & Cryptographic Phone OTP**
- **Problem Solved:** Fast, reliable user verification, order alerts, and multi-factor authentication via SMS across Bangladeshi and international aggregators.
- **Architecture & Highlights:**
  - Unified driver architecture supporting BulkSMSBD, SSL Wireless, Alpha SMS, and a local file/log driver for testing.
  - Pure-PHP 6-digit cryptographic phone OTP generator with rate limiting and exponential backoff.
  - Automatic phone number sanitization (normalizes Bangladeshi numbers from `01XXXXXXXXX`, `+8801XXXXXXXXX`, or `8801XXXXXXXXX`).
  - Pre-built OTP verification workflow: send, throttle, verify, and auto-expire OTPs in the database.
  - Bilingual SMS notification templates with full UTF-8 Unicode support for Bengali text messages.
- **CLI Utilities:** `php bongo sms:send <number> <message>` (dispatches test SMS via active driver).
- **Install:** `php bongo add sms`

---

### 10. `s3` (v1.0.0)
**AWS Signature Version 4 (SigV4) Object Storage Driver**
- **Problem Solved:** Scalable cloud object storage for uploads and backups without AWS's massive 45MB official SDK.
- **Architecture & Highlights:**
  - Complete pure-PHP AWS Signature Version 4 (SigV4) calculation with canonical request hashing, credential scoping, and HMAC-SHA256 signatures.
  - Seamlessly extends Bongo's `Storage` facade via `Storage::disk('s3')`.
  - Compatible with Amazon S3, DigitalOcean Spaces, Cloudflare R2, MinIO, and Backblaze B2.
  - Supports file streaming, multi-part uploads, bucket listing (`ListObjectsV2`), object metadata, and deletion.
  - Generates secure presigned temporary download URLs (`Storage::temporaryUrl($path, $expiration)`).
- **CLI Utilities:** `php bongo s3:check` (validates bucket access, write permissions, and temporary URL generation).
- **Install:** `php bongo add s3`

---

### 11. `ftp` (v1.0.0)
**Zero-Dependency FTP & FTPS Filesystem Storage Driver**
- **Problem Solved:** Legacy server synchronization, remote asset deployment, and CDN mirroring using standard FTP/FTPS protocols.
- **Architecture & Highlights:**
  - Implements a complete FTP/FTPS driver extending Bongo's `Storage` facade (`Storage::disk('ftp')`).
  - Supports passive mode, binary/ASCII transfers, TLS-secured connections (FTPS), and stream socket transport fallbacks.
  - Recursive directory navigation, automated remote path creation, and safe streaming file downloads.
  - Strict timeout handling and connection pooling to avoid server resource exhaustion.
- **CLI Utilities:** `php bongo ftp:check` (tests remote FTP credentials and directory permissions).
- **Install:** `php bongo add ftp`

---

### 12. `file-manager` (v1.0.0)
**Web File Manager & CodeMirror 5 Embedded Code Editor**
- **Problem Solved:** Content managers and developers need an in-browser media manager and code editor that plugs directly into WYSIWYG editors (TinyMCE, CKEditor) with zero Node.js or asset build dependencies.
- **Architecture & Highlights:**
  - Zero server dependencies: pure PHP backend compatible with the UniSharp Laravel File Manager API specification.
  - Professional UI: dark slate header, directory tree sidebar, dual Thumbnails/List views, and speed dial action buttons.
  - Embedded CodeMirror 5 code editor: syntax highlighting for 10+ languages (PHP, JS, CSS, HTML, SQL, Markdown, YAML), Dracula and Eclipse themes, and `Ctrl+S` / `Cmd+S` auto-saving.
  - Media lightbox: high-resolution image preview modal with zoom, rotation, and EXIF metadata display.
  - Standalone modal picker connector `bongoFileManager()` and TinyMCE 5/6 picker callback `bongoTinyMCEPicker()`.
  - Security hardening: strict path traversal sanitization, executable upload blocking (`.exe`, `.phtml`, `.phar`), and MIME validation.
- **Install:** `php bongo add file-manager`

---

### 13. `image` (v1.0.0)
**Pure-PHP Image Processing, WebP Engine & Watermarking**
- **Problem Solved:** High-performance image thumbnailing, responsive avatar generation, and watermark branding without ImageMagick binaries or third-party packages (Intervention Image).
- **Architecture & Highlights:**
  - Pure PHP 8.0+ wrapper over native `ext-gd` with memory management safeguards.
  - Fluent manipulation API: `Image::make($file)->fit(300, 300)->sharpen()->webp(85)->save($path)`.
  - Smart aspect-ratio preservation: exact scaling, aspect-ratio letterboxing, and smart focal-point cropping.
  - Watermarking engine: overlays transparent PNG or SVG watermarks with alpha channel blending and 9-point anchor alignment.
  - Visual filters: grayscale, brightness, contrast, colorize, blur, and invert.
  - Modern WebP and AVIF conversion optimizing file sizes by 30–60% compared to legacy JPEG/PNG.
- **CLI Utilities:** `php bongo image:optimize <directory>` (batch compresses and converts images in a directory to WebP).
- **Install:** `php bongo add image`

---

### 14. `zip` (v1.0.0)
**Pure-PHP ZIP Compression, Streaming & Extraction Engine**
- **Problem Solved:** Creating downloadable user archives, exporting bulk CSV/PDF documents, and extracting uploaded archives safely without Zip-Slip security vulnerabilities.
- **Architecture & Highlights:**
  - Fluent `ZipBuilder` interface for on-the-fly archive assembly: add files, memory strings, and entire directories with glob filtering.
  - In-place `ZipReader` for inspecting archive manifests, file sizes, and checksums without unpacking to disk.
  - Zip-Slip path traversal defense: strictly sanitizes internal archive paths, blocking directory traversal attacks (`../../`).
  - Streaming HTTP downloads directly to the browser with automatic cleanup of temporary spool files.
- **CLI Utilities:**
  - `php bongo zip:make <archive.zip> <folder>`: Compresses folder into a zip archive.
  - `php bongo zip:extract <archive.zip> <destination>`: Safely extracts archive into destination.
  - `php bongo zip:list <archive.zip>`: Inspects contents and file sizes of an archive.
- **Install:** `php bongo add zip`

---

### 15. `backup` (v1.0.0)
**Multi-Database & Application Asset Backup Subsystem**
- **Problem Solved:** Automated, disaster-recovery backup pipelines for databases and uploaded assets without relying on server-level bash scripts or external backup software.
- **Architecture & Highlights:**
  - Multi-database dump engine: native pure-PHP dumpers for SQLite (file snapshot with lock), MySQL/MariaDB (via `mysqldump` or PDO chunked streaming), and PostgreSQL (via `pg_dump` or schema metadata export).
  - Asset archiving: packages application uploads, configuration files, and private storage into timestamped, password-protectable ZIP archives.
  - Multi-disk routing: automatically copies completed backup archives to private local storage (`storage/app/backups`) and remote cloud storage (`Storage::disk('s3')`).
  - Automated retention policies: prunes expired backups based on daily, weekly, and monthly grandfather-father-son retention rules.
- **CLI Utilities:**
  - `php bongo backup:run [--db-only] [--files-only]`: Creates a complete application backup.
  - `php bongo backup:list`: Displays all existing backups, timestamps, disk locations, and archive sizes.
  - `php bongo backup:clean`: Purges backups exceeding the configured retention schedule.
- **Install:** `php bongo add backup`

---

### 16. `api-tools` (v1.0.0)
**API Resources, OpenAPI 3.0 Generator & Rate Limiting**
- **Problem Solved:** Building clean REST APIs, transforming database models into predictable JSON payloads, and generating interactive API documentation without manual OpenAPI YAML authoring.
- **Architecture & Highlights:**
  - Eloquent-style `JsonResource` and `ResourceCollection` transformers with conditional field inclusion (`when()`, `whenLoaded()`) and relation nesting.
  - Standardized `ApiResponse` formatting helper: uniform JSON responses for success, error, validation, and paginated data structures.
  - Automated OpenAPI 3.0 documentation engine: inspects registered API routes and generates complete OpenAPI specs with embedded Swagger UI and Redoc viewer endpoints.
  - Sliding window API rate limiting middleware (`ApiThrottleMiddleware`) with IP and bearer token client isolation and standard HTTP headers (`X-RateLimit-Limit`, `X-RateLimit-Remaining`).
- **CLI Utilities:**
  - `php bongo make:resource <Name>`: Generates a new API transformer class.
  - `php bongo api:routes`: Lists registered API endpoints with middleware and HTTP verbs.
  - `php bongo api:docs`: Compiles OpenAPI 3.0 JSON specification into `public/docs/openapi.json`.
- **Install:** `php bongo add api-tools`

---

### 17. `settings` (v1.0.0)
**Dynamic Database Settings with Type Casting & Caching**
- **Problem Solved:** Storing dynamic application configurations, store settings, and payment credentials in the database rather than hardcoded environment variables, with instant cache invalidation.
- **Architecture & Highlights:**
  - Key-value database storage with automatic type casting: `boolean`, `integer`, `float`, `json`/`array`, and `string`.
  - Multi-tiered performance: in-memory static array cache for current request cycle, backed by persistent cache to eliminate redundant database queries.
  - Global helper `setting('key', $default)` for controllers and middleware.
  - Custom template directive: `@setting('site_name', 'Default')` for zero-boilerplate rendering in views.
  - Contextual groups (e.g. `general`, `payment`, `mail`, `localization`) for structured admin panel forms.
- **CLI Utilities:**
  - `php bongo setting:get <key>`: Reads a setting value from the terminal.
  - `php bongo setting:set <key> <value> [--type=string]`: Updates or creates a setting.
  - `php bongo setting:list [--group=<name>]`: Displays all configured settings in a styled table.
  - `php bongo setting:clear`: Flushes the settings cache.
- **Install:** `php bongo add settings`

---

### 18. `seo` (v1.0.0)
**Search Engine Optimization, OpenGraph, JSON-LD & XML Sitemaps**
- **Problem Solved:** Maximizing search engine rankings and social sharing previews without manual meta tag repetition across templates.
- **Architecture & Highlights:**
  - Fluent metadata builder: `SEO::setTitle('Page')->setDescription('...')->setKeywords([...])->setCanonical('/url')`.
  - Social card automation: OpenGraph (Facebook, LinkedIn) and Twitter Card tags with image dimensions, mime types, and secure URLs.
  - Structured data: Schema.org JSON-LD generation for `Article`, `Product`, `Organization`, `BreadcrumbList`, and `FAQPage`.
  - Dynamic XML Sitemap generator: adheres to `sitemaps.org` 0.9 protocol with automatic lastmod timestamps, change frequencies, and priority weights.
  - Single-line template integration via `@seo` directive in master layouts.
- **CLI Utilities:** `php bongo sitemap:generate` (crawls routes/models and writes `public/sitemap.xml`).
- **Install:** `php bongo add seo`

---

### 19. `activity-log` (v1.0.0)
**Model Activity & Enterprise Audit Trail Logger**
- **Problem Solved:** Compliance, security auditing, and customer support tracking: recording exactly who changed what, when, and from what IP address.
- **Architecture & Highlights:**
  - `LogsActivity` trait: automatically attaches to Bongo models, recording `created`, `updated`, and `deleted` events with zero boilerplate.
  - Old vs new attribute diffing: stores clean JSON snapshots showing only changed fields, with automatic masking of sensitive attributes (passwords, tokens).
  - Client telemetry capture: logs acting user ID, IP address, and browser User-Agent.
  - Standalone activity logger: `Activity::log('order.shipped', $order, ['courier' => 'Steadfast'])`.
  - Retention management: automated cleanup commands to keep the database audit table lean.
- **CLI Utilities:** `php bongo activity:clean [--days=90]` (prunes audit logs older than the retention threshold).
- **Install:** `php bongo add activity-log`

---

### 20. `health` (v1.0.0)
**Production Uptime Monitoring & System Health Checks**
- **Problem Solved:** Providing DevOps teams, load balancers, and external monitoring services (Uptime Kuma, Datadog, Pingdom) with instant system diagnostic telemetry.
- **Architecture & Highlights:**
  - Extensible check runner with built-in checks:
    - **Database:** executes probe queries and measures connection latency in milliseconds across MySQL, PostgreSQL, and SQLite.
    - **Storage:** verifies write, read, and delete operations on configured disks (`public`, `local`, `s3`).
    - **Disk Space:** alerts when disk usage exceeds safe operational thresholds (e.g. >90%).
    - **Cache & Redis:** tests cache responsiveness and memory limits.
  - Public/secured JSON endpoint: `/health` returns overall HTTP 200 (healthy) or HTTP 503 (unhealthy) status with detailed diagnostic breakdown.
  - Secret token authentication: supports optional `?token=...` or `Authorization: Bearer` to prevent public information disclosure.
- **CLI Utilities:** `php bongo health:check` (runs diagnostic suite from terminal and prints health status).
- **Install:** `php bongo add health`

---

### 21. `captcha` (v1.0.0)
**Universal Anti-Bot & Human Verification Suite**
- **Problem Solved:** Automated spam, brute-force form submission, and bots on login, registration, and checkout without mandating external cloud dependencies.
- **Architecture & Highlights:**
  - Multi-driver engine:
    - **Cloudflare Turnstile:** frictionless modern anti-bot protection with theme and size controls.
    - **Google reCAPTCHA:** supports both v2 (checkbox) and v3 (invisible score-based with configurable threshold).
    - **hCaptcha:** privacy-first alternative.
    - **Math Captcha:** zero-dependency session math problems (`8 + 5 = ?`), works offline and in local dev.
    - **Image Captcha:** pure-PHP GD distorted challenge images rendered via Base64 data URI (no dedicated image route required).
  - Cryptographic single-use replay protection and 10-minute session expiry.
  - Template directives: `@captcha` and `@captchascript`.
  - HTTP middleware: `VerifyCaptcha`.
- **Install:** `php bongo add captcha`

---

### 22. `aamarpay` (v1.0.0)
**AamarPay Payment Gateway for Bangladesh**
- **Problem Solved:** Bangladeshi merchants requiring seamless multi-channel checkout across bKash, Nagad, Rocket, Upay, Visa, MasterCard, and local banks.
- **Architecture & Highlights:**
  - Pure-PHP API v2 client using native streams/cURL without Composer wrappers.
  - Dual environment switching (`sandbox` vs `live`) via `.env`.
  - Server-to-server transaction validation with `AamarPay::verify($tranId)`.
  - Pre-built views for checkout, success, fail, and cancel.
  - Optional database audit trail in `aamarpay_transactions` table.
- **CLI Utilities:** `php bongo aamarpay:check` (tests credentials and gateway health).
- **Install:** `php bongo add aamarpay`

---

### 23. `telegram` (v1.0.0)
**Real-Time Telegram Bot Notifications & System Alerts**
- **Problem Solved:** Instant notification delivery for order events, payment alerts, exception tracking, and devops alarms directly to private Telegram chats or channels.
- **Architecture & Highlights:**
  - Pure-PHP Telegram Bot API client.
  - Formatted HTML/Markdown alerts with emojis, app environment, timestamps, and collapsible JSON payloads.
  - Testing fake: `Telegram::fake()` allows complete unit testing without hitting the network.
  - Global helpers: `telegram_notify()` and `telegram_alert()`.
- **CLI Utilities:** `php bongo telegram:send "Hello"`, `php bongo telegram:test`.
- **Install:** `php bongo add telegram`

---

### 24. `bangla-tools` (v1.0.0)
**Bengali Localization & Linguistic Toolkit**
- **Problem Solved:** Formatting numbers, dates, currency, and SEO slugs into authentic Bengali representation for Bangladeshi applications.
- **Architecture & Highlights:**
  - **Digit Converter:** bidirectional English <-> Bengali digit mapping (`12345` <-> `১২৩৪৫`).
  - **Number to Words:** converts integers and decimals to Bengali words (e.g. `150` -> `একশত পঞ্চাশ টাকা মাত্র`, supporting কোটি, লাখ, হাজার, শত, পয়সা).
  - **Bangladeshi Taka Notation:** South Asian grouping (`1,50,000.00` ৳ / `৳ ১,৫০,০০০.০০`).
  - **Bongabdo Calendar Converter:** converts Gregorian dates to Bengali calendar (day, month বৈশাখ-চৈত্র, season গ্রীষ্ম-বসন্ত, Bongabdo year, weekday).
  - **Unicode Normalizer & Slug:** cleans corrupted zero-width spaces (`\u200B`) and creates search-engine friendly Bengali slugs (`আমার-সোনার-বাংলা`).
  - Template directives: `@bangla_number`, `@bangla_date`, `@taka`.
- **Install:** `php bongo add bangla-tools`

---

## 🛠️ Package Management CLI Reference

All packages integrate directly with the Bongo CLI:

```bash
# Add and enable a package in your application
php bongo add <package-name>[@version]

# Install all packages enabled in config/packages.php
php bongo package:install

# Update installed package(s) with local diff safety net
php bongo package:update [<package-name>] [--backup] [--force]

# Remove a package and its files cleanly
php bongo package:remove <package-name> [--force]

# List all available and installed packages with versions
php bongo package:list

# Publish package assets (config, views, lang) for application override
php bongo package:publish <package-name> [--config] [--views] [--lang]

# Inspect local modifications against the package lockfile
php bongo package:diff <package-name>

# Run tests for a specific installed package
php bongo package:test <package-name>
```

---

## 🧪 Running Package Tests

The package suite includes an automated test runner (`run_tests.php`) using Bongo's built-in testing engine via the `BongoMvc` junction:

```bash
# Run tests across all 24 packages
php run_tests.php

# Expected result:
# Tests: 208 passed (853 assertions) across 24 packages
```

---

## 📐 Package Development Standards

All packages in this repository adhere to strict architectural rules:
1. **Zero Required Dependencies:** Pure PHP 8.0+, no mandatory Composer packages, no Node.js runtime.
2. **PSR-12 Standard:** 0 errors verified via `php phpcs.phar`.
3. **Application Override Priority:** Assets published to `resources/views/vendor/<package>/` and `lang/vendor/<package>/` take precedence over package defaults.
4. **Local Diff Safety Net:** `System\Package\Installer` computes SHA-256 hashes against `packages/.lock.json` and prevents accidental overwrite of local developer customizations.
5. **Multi-Database Support:** All database migrations and models must function identically across MySQL, PostgreSQL, and SQLite.

---

## 📄 License

The Bongo PHP Official Packages Suite is open-source software licensed under the [MIT License](LICENSE). Maintained by [Soft Robotics](https://softrobotics.com.bd) and the Bongo PHP Core Team.
