<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Taggable Database Tables
    |--------------------------------------------------------------------------
    |
    | Define the database table names used for storing tags and polymorphic
    | taggable attachments.
    |
    */
    'tags_table' => 'tags',
    'taggables_table' => 'taggables',

    /*
    |--------------------------------------------------------------------------
    | String Tag Delimiter
    |--------------------------------------------------------------------------
    |
    | The delimiter used when passing a string of tags to tag/retag methods.
    |
    */
    'delimiter' => ',',

    /*
    |--------------------------------------------------------------------------
    | Auto-Normalize Tag Names
    |--------------------------------------------------------------------------
    |
    | If enabled, tag names will be trimmed and normalized to lowercase
    | (except for Unicode scripts).
    |
    */
    'normalize_lowercase' => true,
];
