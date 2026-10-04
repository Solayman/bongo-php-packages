# Bongo S3 & Cloud Object Storage Package



<p align="center">
  <img src="docs/screenshot.svg" alt="s3 screenshot" width="800">
</p>
Pure PHP, zero-dependency Amazon S3, DigitalOcean Spaces, Cloudflare R2, and MinIO storage driver for the Bongo Framework.

Uses pure PHP AWS Signature Version 4 (SigV4) authentication. No Composer, AWS SDK, or Node.js runtime required.

---

## 1. Quick Start

### Step 1: Install Package
```bash
php bongo add s3
```

### Step 2: Configure `.env`
Add your object storage credentials:
```env
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=my-app-storage
AWS_URL=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

To make S3 the default storage disk for your application:
```env
STORAGE_DISK=s3
```

### Step 3: Verify Connection
```bash
php bongo s3:check
```

---

## 2. Provider Configurations

### Amazon S3
```env
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=ap-south-1
AWS_BUCKET=my-bongo-bucket
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

### DigitalOcean Spaces
```env
AWS_ACCESS_KEY_ID=DO00...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=nyc3
AWS_BUCKET=my-space-name
AWS_ENDPOINT=https://nyc3.digitaloceanspaces.com
AWS_URL=https://my-space-name.nyc3.cdn.digitaloceanspaces.com
AWS_USE_PATH_STYLE_ENDPOINT=false
```

### Cloudflare R2
```env
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=auto
AWS_BUCKET=my-r2-bucket
AWS_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
AWS_URL=https://pub-<hash>.r2.dev
AWS_USE_PATH_STYLE_ENDPOINT=false
```

### MinIO (Self-Hosted / Local Development)
```env
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=local-bucket
AWS_ENDPOINT=http://127.0.0.1:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
```

---

## 3. Usage Examples

### Store & Retrieve Files
```php
use System\Storage\Storage;

// Upload or write content
Storage::disk('s3')->put('avatars/user-1.jpg', $binaryData);

// Check existence
if (Storage::disk('s3')->exists('avatars/user-1.jpg')) {
    // Read content
    $contents = Storage::disk('s3')->get('avatars/user-1.jpg');
}

// Delete file
Storage::disk('s3')->delete('avatars/user-1.jpg');
```

### Public & CDN URLs
```php
// Resolves virtual-hosted S3 URL or custom AWS_URL / CDN
$url = Storage::disk('s3')->url('products/item-10.jpg');
```

### Secure Presigned Temporary Download URLs
Allow clients to download private files directly from cloud storage without proxying through your web server:
```php
// Generate a 1-hour presigned URL
$url = Storage::disk('s3')->temporaryUrl('invoices/inv-2026-001.pdf', 3600);
```

### Streaming Download Responses
```php
return Storage::disk('s3')->download('exports/customers.csv', 'Customer-Report.csv');
```

### Directory & File Listing
```php
// List immediate directory files
$files = Storage::disk('s3')->files('documents');

// Recursive listing
$allFiles = Storage::disk('s3')->allFiles('documents');

// Delete directory
Storage::disk('s3')->deleteDirectory('temp-folder');
```

---

## 4. Customizing Configuration
Publish the config file to customize disk behavior:
```bash
php bongo package:publish s3 --config
```
This publishes `config/s3.php`.
