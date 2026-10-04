<?php

declare(strict_types=1);

namespace Bongo\Packages\Backup\Dumpers;

interface DumperInterface
{
    /**
     * Dump the database to the specified target file.
     *
     * @param string $targetFile Destination file path.
     * @return int Number of bytes written to the dump file.
     */
    public function dump(string $targetFile): int;
}
