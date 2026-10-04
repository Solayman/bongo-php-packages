<?php

declare(strict_types=1);

namespace Bongo\Packages\Backup\Dumpers;

use Bongo\Packages\Backup\Exceptions\BackupException;
use PDO;

class MySqlDumper implements DumperInterface
{
    public function __construct(
        protected PDO $pdo,
        protected ?string $databaseName = null
    ) {
    }

    public function dump(string $targetFile): int
    {
        $dir = dirname($targetFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $isGzip = str_ends_with(strtolower($targetFile), '.gz');
        $handle = $isGzip ? @gzopen($targetFile, 'wb9') : @fopen($targetFile, 'w');

        if ($handle === false) {
            throw new BackupException("Failed to open MySQL dump destination: {$targetFile}");
        }

        $write = function (string $text) use ($handle, $isGzip): void {
            if ($isGzip) {
                gzwrite($handle, $text);
            } else {
                fwrite($handle, $text);
            }
        };

        $timestamp = date('Y-m-d H:i:s');
        $write("-- --------------------------------------------------------\n");
        $write("-- Bongo Database Backup (MySQL/MariaDB)\n");
        $write("-- Created at: {$timestamp}\n");
        $write("-- --------------------------------------------------------\n\n");
        $write("SET FOREIGN_KEY_CHECKS=0;\n");
        $write("SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\n");
        $write("SET NAMES utf8mb4;\n\n");

        $tablesStmt = $this->pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tables = $tablesStmt ? $tablesStmt->fetchAll(PDO::FETCH_NUM) : [];

        foreach ($tables as $tableRow) {
            $table = $tableRow[0];
            $write("--\n-- Table structure for `{$table}`\n--\n");
            $write("DROP TABLE IF EXISTS `{$table}`;\n");

            $createStmt = $this->pdo->query("SHOW CREATE TABLE `{$table}`");
            $createRow = $createStmt ? $createStmt->fetch(PDO::FETCH_ASSOC) : null;
            if ($createRow && isset($createRow['Create Table'])) {
                $write($createRow['Create Table'] . ";\n\n");
            }

            // Dump rows in batches
            $write("--\n-- Dumping data for `{$table}`\n--\n");
            $dataStmt = $this->pdo->query("SELECT * FROM `{$table}`");
            if ($dataStmt) {
                $batch = [];
                $batchSize = 200;

                while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
                    $escapedVals = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $escapedVals[] = 'NULL';
                        } elseif (is_int($val) || is_float($val)) {
                            $escapedVals[] = (string) $val;
                        } else {
                            $escapedVals[] = $this->pdo->quote((string) $val);
                        }
                    }
                    $batch[] = '(' . implode(', ', $escapedVals) . ')';

                    if (count($batch) >= $batchSize) {
                        $write("INSERT INTO `{$table}` VALUES \n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                }

                if (!empty($batch)) {
                    $write("INSERT INTO `{$table}` VALUES \n" . implode(",\n", $batch) . ";\n");
                }
            }
            $write("\n");
        }

        $write("SET FOREIGN_KEY_CHECKS=1;\n");

        if ($isGzip) {
            gzclose($handle);
        } else {
            fclose($handle);
        }

        $size = filesize($targetFile);
        if ($size === false) {
            throw new BackupException("Failed to determine dump file size: {$targetFile}");
        }

        return $size;
    }
}
