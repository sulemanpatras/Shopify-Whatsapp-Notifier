<?php

return [
    'api_key' => env('SHOPIFY_API_KEY'),
    'api_secret' => env('SHOPIFY_API_SECRET'),
    'scopes' => env('SCOPES', 'read_orders,read_customers'),
    'api_version' => env('SHOPIFY_API_VERSION', '2026-04'),
];
