<?php

return [
    'base_url' => 'https://graph.facebook.com',
    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),
    // Used to complete local phone numbers such as 0300 1234567 -> 92300 1234567
    'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '92'),
    'timeout' => 15,
];
