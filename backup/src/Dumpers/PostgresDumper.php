<?php

declare(strict_types=1);

namespace Bongo\Packages\Backup\Dumpers;

use Bongo\Packages\Backup\Exceptions\BackupException;
use PDO;

class PostgresDumper implements DumperInterface
{
    public function __construct(
        protected PDO $pdo,
        protected string $schema = 'public'
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
            throw new BackupException("Failed to open PostgreSQL dump destination: {$targetFile}");
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
        $write("-- Bongo Database Backup (PostgreSQL)\n");
        $write("-- Created at: {$timestamp}\n");
        $write("-- --------------------------------------------------------\n\n");
        $write("SET search_path = \"{$this->schema}\", pg_catalog;\n\n");

        $tablesStmt = $this->pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE' ORDER BY table_name");
        $tablesStmt->execute([$this->schema]);
        $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $write("--\n-- Table structure for \"{$table}\"\n--\n");
            $write("DROP TABLE IF EXISTS \"{$table}\" CASCADE;\n");

            // Query columns
            $colsStmt = $this->pdo->prepare("
                SELECT column_name, data_type, character_maximum_length, is_nullable, column_default 
                FROM information_schema.columns 
                WHERE table_schema = ? AND table_name = ? 
                ORDER BY ordinal_position
            ");
            $colsStmt->execute([$this->schema, $table]);
            $columns = $colsStmt->fetchAll(PDO::FETCH_ASSOC);

            $colDefs = [];
            foreach ($columns as $col) {
                $def = '"' . $col['column_name'] . '" ' . strtoupper($col['data_type']);
                if ($col['character_maximum_length']) {
                    $def .= "({$col['character_maximum_length']})";
                }
                if ($col['is_nullable'] === 'NO') {
                    $def .= " NOT NULL";
                }
                if ($col['column_default'] !== null) {
                    $def .= " DEFAULT " . $col['column_default'];
                }
                $colDefs[] = $def;
            }

            if (!empty($colDefs)) {
                $write("CREATE TABLE \"{$table}\" (\n  " . implode(",\n  ", $colDefs) . "\n);\n\n");
            }

            // Dump data
            $write("--\n-- Dumping data for \"{$table}\"\n--\n");
            $dataStmt = $this->pdo->query("SELECT * FROM \"{$table}\"");
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
                        } elseif (is_bool($val)) {
                            $escapedVals[] = $val ? 'TRUE' : 'FALSE';
                        } else {
                            $escapedVals[] = $this->pdo->quote((string) $val);
                        }
                    }
                    $batch[] = '(' . implode(', ', $escapedVals) . ')';

                    if (count($batch) >= $batchSize) {
                        $write("INSERT INTO \"{$table}\" VALUES \n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                }

                if (!empty($batch)) {
                    $write("INSERT INTO \"{$table}\" VALUES \n" . implode(",\n", $batch) . ";\n");
                }
            }
            $write("\n");
        }

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
