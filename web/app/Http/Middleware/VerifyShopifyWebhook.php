<?php

namespace App\Http\Middleware;

use App\Services\ShopifyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifyWebhook
{
    public function __construct(private ShopifyService $shopify)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shopify->validWebhookHmac($request->getContent(), $request->header('X-Shopify-Hmac-Sha256'))) {
            return response()->json(['error' => 'Invalid webhook signature'], 401);
        }

        return $next($request);
    }
}
