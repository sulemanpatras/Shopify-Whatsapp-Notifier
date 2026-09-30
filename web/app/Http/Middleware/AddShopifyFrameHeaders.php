<?php

namespace App\Http\Middleware;

use App\Services\ShopifyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Allows the page to be embedded in the Shopify admin (and only there). */
class AddShopifyFrameHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $shop = $request->query('shop');
        $shopHost = ShopifyService::isValidShopDomain($shop) ? "https://{$shop}" : 'https://*.myshopify.com';

        $response->headers->set('Content-Security-Policy', "frame-ancestors {$shopHost} https://admin.shopify.com;");
        $response->headers->remove('X-Frame-Options');

        return $response;
    }
}
