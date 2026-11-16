<?php

/**
 * Inventory Management System configuration.
 * Values come from .env so secrets are never committed to Git.
 */
return [

    // First administrator account, created by AdminUserSeeder.
    // If ADMIN_PASSWORD is empty, a random password is generated and printed once.
    'admin' => [
        'name' => env('ADMIN_NAME', 'System Administrator'),
        'username' => env('ADMIN_USERNAME', 'admin'),
        'email' => env('ADMIN_EMAIL', 'admin@ims.local'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'currencies' => [
        'USD' => ['symbol' => '$', 'decimals' => 2],
        'KHR' => ['symbol' => '៛', 'decimals' => 0],
    ],

];
