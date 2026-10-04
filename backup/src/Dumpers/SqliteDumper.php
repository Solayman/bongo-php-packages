<?php

declare(strict_types=1);

namespace Bongo\Packages\Backup\Dumpers;

use Bongo\Packages\Backup\Exceptions\BackupException;
use PDO;
use Throwable;

class SqliteDumper implements DumperInterface
{
    public function __construct(
        protected ?PDO $pdo = null,
        protected ?string $databasePath = null
    ) {
    }

    public function dump(string $targetFile): int
    {
        $dir = dirname($targetFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $isGzip = str_ends_with(strtolower($targetFile), '.gz');
        $rawTarget = $isGzip ? preg_replace('/\.gz$/i', '', $targetFile) : $targetFile;

        $dbFile = $this->databasePath;

        if ($dbFile === null && $this->pdo !== null) {
            try {
                $stmt = $this->pdo->query("PRAGMA database_list");
                $databases = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                foreach ($databases as $db) {
                    if (($db['name'] ?? '') === 'main' && !empty($db['file'])) {
                        $dbFile = $db['file'];
                        break;
                    }
                }
            } catch (Throwable) {
                // Ignore pragma lookup failure
            }
        }

        if ($dbFile !== null && file_exists($dbFile) && $dbFile !== ':memory:') {
            $dumped = false;

            // Method 1: VACUUM INTO for live atomic backup
            if ($this->pdo !== null) {
                try {
                    if (file_exists($rawTarget)) {
                        @unlink($rawTarget);
                    }
                    $escaped = str_replace("'", "''", $rawTarget);
                    $this->pdo->exec("VACUUM INTO '{$escaped}'");
                    $dumped = file_exists($rawTarget) && filesize($rawTarget) > 0;
                } catch (Throwable) {
                    $dumped = false;
                }
            }

            // Method 2: Direct file copy fallback
            if (!$dumped) {
                if (!@copy($dbFile, $rawTarget)) {
                    throw new BackupException("Failed to copy SQLite database file from [{$dbFile}] to [{$rawTarget}].");
                }
            }
        } else {
            // Method 3: Pure-SQL dump for :memory: or when file path is unavailable
            $this->dumpSqlStatements($rawTarget);
        }

        if ($isGzip) {
            $data = file_get_contents($rawTarget);
            if ($data === false) {
                throw new BackupException("Failed to read raw SQLite dump for compression.");
            }
            $compressed = gzencode($data, 9);
            if ($compressed === false) {
                throw new BackupException("Failed to gzip compress SQLite dump.");
            }
            file_put_contents($targetFile, $compressed);
            @unlink($rawTarget);
        }

        $size = filesize($targetFile);
        if ($size === false) {
            throw new BackupException("Failed to determine dump file size for [{$targetFile}].");
        }

        return $size;
    }

    protected function dumpSqlStatements(string $destFile): void
    {
        if ($this->pdo === null) {
            throw new BackupException("No PDO connection available to dump SQLite schema.");
        }

        $handle = fopen($destFile, 'w');
        if ($handle === false) {
            throw new BackupException("Unable to open [{$destFile}] for writing.");
        }

        fwrite($handle, "PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n");

        $stmt = $this->pdo->query("SELECT sql FROM sqlite_master WHERE type IN ('table', 'index', 'trigger', 'view') AND sql IS NOT NULL ORDER BY type = 'table' DESC");
        $schema = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($schema as $row) {
            fwrite($handle, $row['sql'] . ";\n");
        }

        $tablesStmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        $tables = $tablesStmt ? $tablesStmt->fetchAll(PDO::FETCH_COLUMN) : [];

        foreach ($tables as $table) {
            $dataStmt = $this->pdo->query("SELECT * FROM \"{$table}\"");
            if ($dataStmt) {
                while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
                    $cols = array_map(fn($c) => '"' . $c . '"', array_keys($row));
                    $vals = array_map(function ($val) {
                        if ($val === null) {
                            return 'NULL';
                        }
                        if (is_int($val) || is_float($val)) {
                            return (string) $val;
                        }
                        return $this->pdo->quote((string) $val);
                    }, array_values($row));

                    fwrite($handle, "INSERT INTO \"{$table}\" (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n");
                }
            }
        }

        fwrite($handle, "COMMIT;\n");
        fclose($handle);
    }
}
