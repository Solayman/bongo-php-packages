<?php

use Bongo\Packages\S3\S3Disk;
use System\Storage\Storage;

if (class_exists(Storage::class) && method_exists(Storage::class, 'extend')) {
    Storage::extend('s3', function (array $config, string $name) {
        return new S3Disk($name, $config);
    });
}
