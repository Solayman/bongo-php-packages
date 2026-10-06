# Project Instructions & Memory — Bongo PHP Packages Suite

## 1. Multi-Repository Workspace Structure (`D:\laravel\framework\`)
- **`bongo-php`**: Core framework repository (`solayman` branch for active development, `master` for production).
- **`bongo-php-packages`**: Official package suite repository containing all 40 packages (`sslcommerz`, `bkash`, `sms`, `s3`, `social-login`, `nagad`, `stripe`, `two-factor`, `image`, `backup`, `permission`, `zip`, `ftp`, `paypal`, `api-tools`, `settings`, `seo`, `activity-log`, `health`, `file-manager`, `captcha`, `aamarpay`, `telegram`, `bangla-tools`, `bd-locations`, `webhooks`, `impersonate`, `queue-monitor`, `discord`, `toast`, `sweet-alert`, `zoom`, `google-meet`, `courier`, `invoice`, `whatsapp`, `currency`, `coupon`, `push-notification`, `geoip`) with 299 passing package tests (1,239 assertions).
- **`bongo-package-and-feature-test`**: Official testbed and application sandbox with 42 passing feature tests (449 assertions). **RULE: Always implement and test new features in this repository.** (`bongo-test` junction links here).
- **`BongoMvc`**: Directory junction linking to `bongo-php` for seamless package test runner (`run_tests.php`) execution.
- **`bongo-test`**: Directory junction linking to `bongo-package-and-feature-test`.

---

## 2. Git, Branching & Merge Request (MR) Policy
- **Full MR & Merge Access**: The AI agent has full authority to make Pull Requests / Merge Requests (MRs) and merge them.
- **Package MR Rule**: **If any package needs an MR, go with `bongo-php-packages`.**
- **Always Use `solayman` Branch**: All package development, additions, and updates must be committed to the `solayman` branch.
- **Always Make MR with `solayman` & Merge**:
  1. Commit code to `solayman`.
  2. Push to `origin/solayman`.
  3. Create PR/MR targeting `master` with `head: solayman` (via GitHub REST API).
  4. Merge the PR/MR into `master` immediately (`merge_method: merge`).
  5. Sync local `master` and `solayman` branches (`git merge origin/master`).

---

## 3. Environment & Credential Policy
- **Check `.env` for `GITHUB_TOKEN`**: Every repository maintains a `.env` file containing a dedicated `GITHUB_TOKEN` (Fine-grained PAT) for API operations, package catalog lookups, PR creation, and merges:
  - `bongo-php/.env`: `GITHUB_TOKEN`
  - `bongo-package-and-feature-test/.env`: `GITHUB_TOKEN`
  - `bongo-php-packages/.env`: `GITHUB_TOKEN`
- Never remove, overwrite, or expose credentials. Use them for authenticated GitHub API operations.

---

## 4. Feature Development & Application Sandbox Rule
- **Always Do Features in `bongo-package-and-feature-test`**: New features, integrations, and demo workflows must always be implemented and tested in the `bongo-package-and-feature-test` repository sandbox before any core release.

---

## 5. Official Packages Suite Overview (40 Packages)
1. **`sslcommerz`** (1.0.0): SSLCommerz payment gateway for Bangladesh with token validation, sandbox/live modes, IPN handling, checkout views, translations, and `php bongo sslcommerz:check`.
2. **`bkash`** (1.0.0): bKash Tokenized Checkout payment gateway with token caching, payment execution, query, refund, checkout views, translations, and `php bongo bkash:check`.
3. **`sms`** (1.0.0): Multi-driver SMS gateway (BulkSMSBD, SSL Wireless, Alpha SMS, and log driver) with 6-digit cryptographic phone OTP generation, attempt throttling, verification, views, and natural Bengali localization.
4. **`s3`** (1.0.0): Pure-PHP AWS Signature Version 4 (SigV4) object storage disk for Amazon S3, DigitalOcean Spaces, Cloudflare R2, and MinIO with presigned temporary URLs and `php bongo s3:check`.
5. **`social-login`** (1.0.0): Google, GitHub, and Facebook OAuth 2.0 social login with CSRF state protection, private email resolution, user model auto-creation, SVG button partials, and natural Bengali localization.
6. **`nagad`** (1.0.0): Direct Nagad Mobile Financial Service (MFS) payment gateway with native OpenSSL RSA-2048 encryption, SHA256withRSA signatures, two-step checkout flow, verification, refund, CLI key generator (`php bongo nagad:keys --save`), and `php bongo nagad:check`.
7. **`stripe`** (1.0.0): Zero-dependency Stripe international payment gateway with Checkout Sessions, Payment Intents, full/partial refunds, HMAC-SHA256 webhook signature verification, UI components, and `php bongo stripe:check`.
8. **`two-factor`** (1.0.0): RFC 6238 TOTP two-factor authentication with pure-PHP SVG QR code matrix generator (using Galois Field $GF(2^8)$ and Reed-Solomon polynomial error correction), single-use hashed recovery backup codes, middleware, bilingual views, and `php bongo 2fa:generate`.
9. **`image`** (1.0.0): Pure-PHP image manipulation, smart cropping, thumbnailing, watermarking with alpha blending, visual filters, and WebP compression engine powered by PHP `ext-gd` with zero third-party dependencies, plus `php bongo image:optimize`.
10. **`backup`** (1.0.0): Zero-dependency multi-database (SQLite, MySQL, PostgreSQL) and application asset backup engine with `ZipArchive` packaging, automatic retention cleanup, multi-disk routing (local and cloud S3), and CLI suite (`php bongo backup:run`, `backup:list`, `backup:clean`).
11. **`permission`** (1.0.0): Role-Based Access Control (RBAC) and permissions management subsystem with `HasRoles` trait, super-admin bypass, `RoleMiddleware`, `PermissionMiddleware`, multi-tenant permission migrations, and CLI suite (`role:create`, `permission:create`, `role:assign`).
12. **`zip`** (1.0.0): Pure-PHP ZIP compression and extraction engine with fluent `ZipBuilder`, in-place `ZipReader` inspection, Zip-Slip path traversal protection, streaming HTTP downloads, and CLI commands (`zip:make`, `zip:extract`, `zip:list`).
13. **`ftp`** (1.0.0): Zero-dependency FTP and FTPS filesystem driver extending Bongo's `Storage` facade via `Storage::disk('ftp')`, pure-PHP stream socket transport fallback, and `php bongo ftp:check`.
14. **`paypal`** (1.0.0): PayPal REST API v2 Checkout payment gateway with hosted redirect approvals, payment capture, authorization holds, full/partial refunds, cryptographically verified webhooks, responsive views, and `php bongo paypal:check`.
15. **`api-tools`** (1.0.0): API development suite featuring Eloquent-style `JsonResource` and `ResourceCollection` transformers with conditional inclusion, standardized `ApiResponse` formatting, automated OpenAPI 3.0 (Swagger UI & Redoc) documentation generation, sliding window API rate limiting middleware (`ApiThrottleMiddleware`), CLI suite (`api:routes`, `api:docs`, `make:resource`), `@summary`/`@description`/`@tags` docblock annotations, custom spec merge, in-browser editor compatibility, and `public/docs/openapi.json` route serving to prevent built-in server `/api/` path collisions (12 tests, 68 assertions).
16. **`settings`** (1.0.0): Dynamic application & store settings in database with type casting (`boolean`, `integer`, `float`, `json`, `string`), in-memory and persistent caching, helper `setting()`, `@setting` directive, and CLI suite (`setting:get`, `setting:set`, `setting:list`, `setting:clear`).
17. **`seo`** (1.0.0): Comprehensive Search Engine Optimization suite with fluent metadata builder, OpenGraph, Twitter Cards, Schema.org JSON-LD structured data, dynamic XML Sitemap generator adhering to `sitemaps.org` 0.9, `@seo` directive, and `sitemap:generate`.
18. **`activity-log`** (1.0.0): Enterprise-ready audit trail and model activity logger with `LogsActivity` trait, automated event recording and old vs new attribute diff tracking, client telemetry (IP and User-Agent), and `php bongo activity:clean`.
19. **`health`** (1.0.0): Production uptime monitoring and health check suite with built-in checks for Database latency, Storage write/read/delete, Disk Space %, Cache responsiveness, and Redis, HTTP `/health` JSON endpoint with secret token masking, and CLI `php bongo health:check`.
20. **`file-manager`** (1.0.0): Zero-dependency web file manager and embedded professional code editor compatible with UniSharp File Manager API. Features dark slate header, directory tree sidebar, dual Thumbnails/List views, speed dial floating action buttons (FAB), drag-and-drop multi-file uploads, embedded CodeMirror 5 code editor with syntax highlighting for 10+ languages (PHP, JS, CSS, HTML, SQL, etc.), Dracula/Eclipse themes, `Ctrl+S` auto-saving, high-resolution image preview lightbox with zoom and metadata, standalone WYSIWYG connector (`bongoFileManager()`), and path traversal sanitization.
21. **`captcha`** (1.0.0): Universal anti-bot human verification suite (Cloudflare Turnstile, Google reCAPTCHA v2/v3, hCaptcha, pure-PHP offline math & GD image challenges with replay protection, `@captcha` / `@captchascript` directives, and `VerifyCaptcha` middleware).
22. **`aamarpay`** (1.0.0): AamarPay payment gateway for Bangladesh with sandbox/live modes, MFS support (bKash, Nagad, Rocket, Upay), card processing, `TrxCheck` server validation, pre-built views, and `php bongo aamarpay:check` CLI command.
23. **`telegram`** (1.0.0): Real-time Telegram Bot notifications, rich system alerts with emojis and JSON details, channel broadcasts, `Telegram::fake()` unit testing, and `php bongo telegram:send` / `telegram:test` CLI utilities.
24. **`bangla-tools`** (1.0.0): Authentic Bengali linguistic toolkit (English <-> Bengali digit conversion, number-to-words engine with কোটি/লাখ/হাজার/শত, South Asian Taka currency notation, Bongabdo calendar converter according to Bangla Academy standards, Unicode cleaner, and SEO slug generator).
25. **`bd-locations`** (1.0.0): Complete Bangladesh administrative divisions, 64 districts, upazilas, postcodes, and shipping zones in English and Bengali with HTML cascading select generators and CLI command `bd-locations:list`.
26. **`webhooks`** (1.0.0): Outbound webhook dispatcher with HMAC-SHA256 signature headers, retry support, `Webhook::fake()` unit testing, and CLI command `webhook:send`.
27. **`impersonate`** (1.0.0): Admin user impersonation with secure session swapping, session guarding, floating leave bar, and activity log tracking.
28. **`queue-monitor`** (1.0.0): Dark-themed web dashboard for Bongo background queue jobs, pending counts, driver telemetry, and failed job retries/flushing.
29. **`discord`** (1.0.0): Discord webhook alerts, notifications, and rich embed builder with color-coded severity levels, global helpers, and CLI commands `discord:send` / `discord:test`.
30. **`toast`** (1.0.0): Zero-dependency flash and dynamic toast notification system with animated progress duration bar, auto-dismiss timers, custom positions, session flash integration (`toast()->success(...)`), and client JS API.
31. **`sweet-alert`** (1.0.0): Modern zero-dependency modal alert and confirmation dialog engine with promise callbacks, danger prompts, backdrop blur, ESC key dismiss, and `SweetAlert::dangerConfirm(...)`.
32. **`zoom`** (1.0.0): Zoom Video Communications API client with Server-to-Server OAuth authorization, meeting management (`zoom()->createMeeting(...)`), registrants, CRC URL validation, and HMAC-SHA256 webhook verification.
33. **`google-meet`** (1.0.0): Google Meet and Calendar API client with pure-PHP Service Account RS256 JWT authorization, meeting generation (`hangoutLink`), attendee invites, and CLI command `meet:create`.
34. **`courier`** (1.0.0): Bangladesh courier and logistics engine for Steadfast and Pathao Courier with automated consignment creation, live delivery tracking, merchant balance inquiry, unified webhook normalizer, and CLI command `courier:track`.
35. **`invoice`** (1.0.0): Professional invoice and POS thermal receipt generator with fluent builder, subtotal/tax/discount/shipping calculation, print-optimized A4 `@media print` layout, 80mm/58mm thermal receipt layout (`renderPos()`), XSS protection, and `invoice()` global helper.
36. **`whatsapp`** (1.0.0): Meta WhatsApp Business Cloud API client for transactional messages, 6-digit OTP template verification, media dispatch (invoices/images/video), webhook subscription handshake, HMAC-SHA256 signature verification, `WhatsApp::fake()` testing, and CLI `whatsapp:send`.
37. **`currency`** (1.0.0): Multi-currency conversion, live exchange rate caching, base-currency bridging, and localized money formatting engine (`৳1,250.00`, `$99.99`, `150.00 SAR`) with CLI command `currency:update`.
38. **`coupon`** (1.0.0): E-commerce promotions and vouchers engine supporting percentage discounts (with max cap), fixed discounts, free shipping, minimum order thresholds, usage caps, and CLI command `coupon:check`.
39. **`push-notification`** (1.0.0): Zero-dependency Web Push (VAPID RFC 8292) and Firebase Cloud Messaging (FCM v1) client with JWT ES256 authentication, payload formatting, offline mock, and CLI command `push:send`.
40. **`geoip`** (1.0.0): IP address geolocation, visitor country/city/timezone/currency resolution, reverse proxy and Cloudflare IP extraction, in-memory caching, and CLI command `geoip:lookup`.

---

## 6. Package Development Standards
- **Zero Required External Dependencies**: Pure PHP 8.0+, no Composer packages, no Node.js runtime.
- **PSR-12 Standard**: 0 errors checked via `php phpcs.phar`.
- **Test Suite**: Run `php run_tests.php` to verify all 299 package tests (1,239 assertions) across all 40 packages with 0 errors.
- **Namespaced Assets**: Views must use `name::view` and translations must use `name::key`.
- **App Override Priority**: Never hardcode paths that prevent `resources/views/vendor/<name>/` overrides.
