<?php
/*
 * Goal Tracker configuration.
 *
 * Copy this file to "config.php" (same folder) and edit the values below.
 * config.php is blocked from web access by .htaccess.
 */
return [
    // Title shown at the top of every page.
    'site_title' => 'Professional Development Goals',

    // Optional line shown under the title on the public page. '' to hide.
    'site_subtitle' => 'Progress toward my current certifications, degrees and continuing education.',

    // Your local time zone (used for "today" and pace calculations).
    'timezone' => 'America/Denver',

    // Database. Choose ONE driver: 'sqlite' (no setup needed) or 'mysql'.
    'db' => [
        'driver' => 'sqlite',

        // SQLite: a single file. The data/ folder is blocked from web access.
        'sqlite_path' => __DIR__ . '/data/goals.sqlite',

        // MySQL: create the database and user in cPanel > MySQL Databases.
        // HostGator prefixes names with your cPanel username, e.g. "jdoe_goals".
        'mysql' => [
            'host' => 'localhost',
            'name' => 'cpaneluser_goals',
            'user' => 'cpaneluser_goals',
            'pass' => 'change-me',
        ],
    ],
];
