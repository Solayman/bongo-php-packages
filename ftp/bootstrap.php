<?php

use Bongo\Packages\Ftp\FtpDisk;
use System\Storage\Storage;

if (class_exists(Storage::class) && method_exists(Storage::class, 'extend')) {
    Storage::extend('ftp', function (array $config, string $name) {
        return new FtpDisk($name, $config);
    });
}
