# FTP Storage Driver for Bongo PHP Framework

Official zero-dependency FTP and FTPS filesystem driver and client for Bongo PHP Framework. Connects Bongo's `Storage` facade directly to remote FTP servers, supporting both native PHP `ext-ftp` and pure-PHP raw socket transport fallback with zero required extensions.

---

## 1. Quick Start

### Step 1: Install Package
```bash
php bongo add ftp
```

### Step 2: Configure `.env`
```ini
FTP_HOST=ftp.example.com
FTP_PORT=21
FTP_USERNAME=myuser
FTP_PASSWORD=secret
FTP_ROOT=/public_html
FTP_PASSIVE=true
FTP_SSL=false
```

### Step 3: Verify Connectivity
```bash
php bongo ftp:check
```

---

## 2. Usage via Storage Facade

Declare disk in `config/storage.php`:
```php
'disks' => [
    'ftp' => [
        'driver'   => 'ftp',
        'host'     => env('FTP_HOST'),
        'port'     => (int) env('FTP_PORT', 21),
        'username' => env('FTP_USERNAME'),
        'password' => env('FTP_PASSWORD'),
        'root'     => env('FTP_ROOT', '/'),
        'passive'  => (bool) env('FTP_PASSIVE', true),
        'ssl'      => (bool) env('FTP_SSL', false),
    ],
],
```

Then use anywhere in your application:
```php
use System\Storage\Storage;

// Upload file to FTP server
Storage::disk('ftp')->put('backups/db.sql', $data);

// Download file contents
$contents = Storage::disk('ftp')->get('backups/db.sql');

// File existence & size
$exists = Storage::disk('ftp')->exists('backups/db.sql');
$bytes  = Storage::disk('ftp')->size('backups/db.sql');

// List directory files
$files  = Storage::disk('ftp')->files('backups');

// Delete remote file
Storage::disk('ftp')->delete('backups/db.sql');
```

---

## 3. Direct FtpClient Usage

```php
use Bongo\Packages\Ftp\FtpClient;

$client = new FtpClient([
    'host'     => 'ftp.example.com',
    'username' => 'user',
    'password' => 'secret',
]);

$client->upload('remote/path.txt', 'File contents');
$content = $client->download('remote/path.txt');
$client->delete('remote/path.txt');
```

---

## 4. Testing

Run package test suite:
```bash
php run_tests.php ftp
```
