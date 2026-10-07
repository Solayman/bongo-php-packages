<?php

return [
    // Zip bomb protection for Zip::extract() / ZipReader::extractTo()
    'max_entries' => (int) env('ZIP_MAX_ENTRIES', 10000),
    'max_extract_bytes' => (int) env('ZIP_MAX_EXTRACT_BYTES', 1073741824),   // 1 GB, counted on bytes written
];
