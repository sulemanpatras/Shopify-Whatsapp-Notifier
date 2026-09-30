<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ShopifyService;
use Illuminate\Http\Request;

class AppController extends Controller
{
    public function index(Request $request, ShopifyService $shopify)
    {
        $shop = $request->query('shop');

        if (! ShopifyService::isValidShopDomain($shop)) {
            return response()->view('open-from-shopify', [], 400);
        }

        // If Shopify signed the URL, make sure the signature is valid.
        if ($request->query('hmac') && ! $shopify->validQueryHmac($request->query())) {
            abort(400, 'Invalid request signature');
        }

        // Opened outside the Shopify admin iframe: send the merchant to the embedded app.
        if (! $request->query('embedded')) {
            $handle = str_replace('.myshopify.com', '', $shop);

            return redirect()->away("https://admin.shopify.com/store/{$handle}/apps/".config('shopify.api_key'));
        }

        return view('admin', ['apiKey' => config('shopify.api_key'), 'shop' => $shop]);
    }
}
