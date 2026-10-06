<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SweetAlert Modal Configuration
    |--------------------------------------------------------------------------
    |
    | Default texts and colors for alerts and confirmation dialogs.
    |
    */
    'confirm_button_text' => 'OK',
    'cancel_button_text'  => 'Cancel',
    'confirm_button_color' => '#2563eb',
    'danger_button_color'  => '#dc2626',
    'cancel_button_color'  => '#6b7280',

    /*
    | Whether clicking outside the modal (backdrop) dismisses it.
    */
    'allow_outside_click' => true,

    /*
    | Whether the escape key dismisses the dialog.
    */
    'allow_escape_key' => true,

    /*
    | Session flash key used for flashing alerts between redirects.
    */
    'session_key' => 'bongo_sweet_alerts',
];
