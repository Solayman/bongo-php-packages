# Backup Manager for Bongo PHP Framework

Official zero-dependency database and application asset backup package for Bongo PHP Framework. Create atomic snapshots of your MySQL, PostgreSQL, or SQLite database alongside user uploads and storage files into compressed `.zip` archives with automatic retention cleanup and optional cloud storage (AWS S3, Cloudflare R2, MinIO).

---

## 1. Quick Start

### Step 1: Install Package
```bash
php bongo add backup
```

### Step 2: Configure `.env` (Optional)
```ini
BACKUP_DISK=local
BACKUP_KEEP_COUNT=10
BACKUP_KEEP_DAYS=30
```

### Step 3: Run Backup via CLI
```bash
# Full backup (database + storage files)
php bongo backup:run

# Database-only backup
php bongo backup:run --db-only

# Files-only backup
php bongo backup:run --files-only

# Store directly to S3 cloud storage
php bongo backup:run --disk=s3
```

---

## 2. CLI Commands

### A. Create Backup (`backup:run`)
```bash
php bongo backup:run [options]

# Options:
#   --db-only      Only dump the database
#   --files-only   Only archive storage/app and public/storage files
#   --disk=local   Target disk ('local' or 's3')
#   --database=db  Specific database connection to dump
```

### B. List Backups (`backup:list`)
```bash
php bongo backup:list [--disk=local]

# Output:
#   FILENAME                                 SIZE         DATE                 DISK    
#   ------------------------------------------------------------------------------------
#   bongo_backup_2026-10-04_174000.zip       14.2 MB      2026-10-04 17:40:00  local   
#   bongo_backup_2026-10-03_030000.zip       13.8 MB      2026-10-03 03:00:00  local   
```

### C. Clean Expired Backups (`backup:clean`)
```bash
# Clean based on retention policy (default: keep newest 10, delete older than 30 days)
php bongo backup:clean

# Explicitly keep only the newest 5 backups
php bongo backup:clean --keep=5
```

---

## 3. Programmatic Usage

### A. Trigger Backup from Code / Cron Job
```php
use Bongo\Packages\Backup\Backup;

$result = Backup::run([
    'db_only' => false,
    'disk' => 'local', // or 's3'
]);

if ($result->isSuccessful()) {
    echo "Backup created: " . $result->path() . " (" . $result->humanSize() . ")\n";
    echo "Duration: " . $result->duration() . "s\n";
} else {
    echo "Backup failed: " . implode(', ', $result->messages()) . "\n";
}
```

### B. List Backups
```php
use Bongo\Packages\Backup\Backup;

$backups = Backup::list('local');
foreach ($backups as $b) {
    echo "{$b['filename']} - {$b['human_size']} ({$b['date']})\n";
}
```

### C. Retention Cleanup
```php
use Bongo\Packages\Backup\Backup;

// Keep latest 7 backups
$deletedCount = Backup::clean(disk: 'local', keepCount: 7, keepDays: 14);
```

---

## 4. Multi-Database Engine Architecture

- **SQLite**: Zero-lock atomic live backup via `VACUUM INTO` (or direct file copy) with optional Gzip compression.
- **MySQL / MariaDB**: Pure-PHP DDL and chunked data dumper with `SET FOREIGN_KEY_CHECKS=0` and proper escaping.
- **PostgreSQL**: Native schema introspection and table DDL + batch row exporter.

---

## 5. Automated Scheduling in Bongo

Add backup creation to your console scheduler or cron job:

```bash
# Daily backup at 3:00 AM
0 3 * * * cd /path/to/bongo && php bongo backup:run --disk=s3 >> storage/logs/backup.log 2>&1
```

---

## 6. Testing

Run package test suite:
```bash
php run_tests.php backup
```
