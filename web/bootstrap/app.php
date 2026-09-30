<?php

use App\Http\Middleware\AddShopifyFrameHeaders;
use App\Http\Middleware\VerifyShopifySessionToken;
use App\Http\Middleware\VerifyShopifyWebhook;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The app runs behind the Shopify CLI tunnel / a reverse proxy.
        $middleware->trustProxies(at: '*');

        // Webhooks are signed with HMAC and the admin API uses Shopify session tokens, not cookies.
        $middleware->validateCsrfTokens(except: ['webhooks/*', 'api/*']);

        $middleware->alias([
            'shopify.webhook' => VerifyShopifyWebhook::class,
            'shopify.session' => VerifyShopifySessionToken::class,
            'shopify.frame' => AddShopifyFrameHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
