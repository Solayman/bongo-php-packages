# Project Instructions & Memory — Bongo PHP Packages Suite

## 1. Multi-Repository Workspace Structure (`D:\laravel\framework\`)
- **`bongo-php`**: Core framework repository (`solayman` branch for active development, `master` for production).
- **`bongo-php-packages`**: Official package suite repository containing all 20 packages (`sslcommerz`, `bkash`, `sms`, `s3`, `social-login`, `nagad`, `stripe`, `two-factor`, `image`, `backup`, `permission`, `zip`, `ftp`, `paypal`, `api-tools`, `settings`, `seo`, `activity-log`, `health`, `file-manager`) with 174 passing package tests (706 assertions).
- **`bongo-package-and-feature-test`**: Official testbed and application sandbox. **RULE: Always implement and test new features in this repository.** (`bongo-test` junction links here).
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

## 5. Official Packages Suite Overview (20 Packages)
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
15. **`api-tools`** (1.0.0): API development suite featuring Eloquent-style `JsonResource` and `ResourceCollection` transformers with conditional inclusion, standardized `ApiResponse` formatting, automated OpenAPI 3.0 (Swagger UI & Redoc) documentation generation, sliding window API rate limiting middleware (`ApiThrottleMiddleware`), and CLI suite (`api:routes`, `api:docs`, `make:resource`).
16. **`settings`** (1.0.0): Dynamic application & store settings in database with type casting (`boolean`, `integer`, `float`, `json`, `string`), in-memory and persistent caching, helper `setting()`, `@setting` directive, and CLI suite (`setting:get`, `setting:set`, `setting:list`, `setting:clear`).
17. **`seo`** (1.0.0): Comprehensive Search Engine Optimization suite with fluent metadata builder, OpenGraph, Twitter Cards, Schema.org JSON-LD structured data, dynamic XML Sitemap generator adhering to `sitemaps.org` 0.9, `@seo` directive, and `sitemap:generate`.
18. **`activity-log`** (1.0.0): Enterprise-ready audit trail and model activity logger with `LogsActivity` trait, automated event recording and old vs new attribute diff tracking, client telemetry (IP and User-Agent), and `php bongo activity:clean`.
19. **`health`** (1.0.0): Production uptime monitoring and health check suite with built-in checks for Database latency, Storage write/read/delete, Disk Space %, Cache responsiveness, and Redis, HTTP `/health` JSON endpoint with secret token masking, and CLI `php bongo health:check`.
20. **`file-manager`** (1.0.0): Zero-dependency web file manager and embedded professional code editor compatible with UniSharp File Manager API. Features dark slate header, directory tree sidebar, dual Thumbnails/List views, speed dial floating action buttons (FAB), drag-and-drop multi-file uploads, embedded CodeMirror 5 code editor with syntax highlighting for 10+ languages (PHP, JS, CSS, HTML, SQL, etc.), Dracula/Eclipse themes, `Ctrl+S` auto-saving, high-resolution image preview lightbox with zoom and metadata, standalone WYSIWYG connector (`bongoFileManager()`), and path traversal sanitization.

---

## 6. Package Development Standards
- **Zero Required External Dependencies**: Pure PHP 8.0+, no Composer packages, no Node.js runtime.
- **PSR-12 Standard**: 0 errors checked via `php phpcs.phar`.
- **Test Suite**: Run `php run_tests.php` to verify all 174 package tests (706 assertions).
- **Namespaced Assets**: Views must use `name::view` and translations must use `name::key`.
- **App Override Priority**: Never hardcode paths that prevent `resources/views/vendor/<name>/` overrides.
