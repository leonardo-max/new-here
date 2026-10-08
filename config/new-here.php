<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Panels
    |--------------------------------------------------------------------------
    |
    | New Here registers itself on every Filament panel, so installing the
    | package is enough. Use a list of panel ids to limit it, or `false` to
    | turn auto-registration off and add `NewHerePlugin::make()` yourself.
    |
    */

    'panels' => '*',

    /*
    |--------------------------------------------------------------------------
    | How long something stays "new"
    |--------------------------------------------------------------------------
    |
    | A feature is highlighted from its `since` date until this many days have
    | passed. After that, the `->isNew()` call becomes a no-op and can be
    | removed at your leisure (`php artisan new-here:list --expired`).
    |
    */

    'expires_after_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Highlights per page
    |--------------------------------------------------------------------------
    |
    | The maximum number of hints shown at once. The rest wait in line and
    | appear after the user dismisses the current ones.
    |
    */

    'max_per_page' => 3,

    /*
    |--------------------------------------------------------------------------
    | Open the first hint on page load
    |--------------------------------------------------------------------------
    |
    | When disabled, only the beacons pulse and the hint opens on click.
    |
    */

    'open_first_hint_automatically' => true,

    /*
    |--------------------------------------------------------------------------
    | Do not show the past to newcomers
    |--------------------------------------------------------------------------
    |
    | When enabled, a feature released before the user account was created is
    | not highlighted for that user: everything is new to them, so nothing is.
    |
    */

    'ignore_features_older_than_user' => true,

    'user_created_at_attribute' => 'created_at',

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */

    'table' => 'new_here_seen',

    /*
    | Rows older than this are deleted by `php artisan new-here:prune`
    | (schedule it daily). Keep it longer than your longest announcement.
    */

    'seen_retention_days' => 400,

    /*
    | Keys come from the browser: this ceiling stops a scripted client from
    | filling the table. Real users dismiss a handful of hints a week.
    */

    'max_seen_per_user' => 5000,

];
