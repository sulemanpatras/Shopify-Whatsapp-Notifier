<?php

namespace App\Http\Middleware;

use App\Services\ShopifyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Authenticates admin panel API calls with the App Bridge session token
 * (no cookies needed, which matters inside the Shopify admin iframe).
 */
class VerifyShopifySessionToken
{
    public function __construct(private ShopifyService $shopify)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return $this->unauthorized('Missing session token');
        }

        try {
            $decoded = $this->shopify->decodeSessionToken($token);
            $shop = $this->shopify->resolveShop($decoded['shop'], $token);
        } catch (Throwable $e) {
            return $this->unauthorized($e->getMessage());
        }

        $request->attributes->set('shop', $shop);

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        // Tells App Bridge to fetch a fresh token and retry once.
        return response()->json(['error' => $message], 401)
            ->header('X-Shopify-Retry-Invalid-Session-Request', '1');
    }
}
