<?php

namespace App\Services;

use App\Models\MessageTemplate;
use App\Models\NotificationSetting;
use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ShopifyService
{
    public const SHOP_REGEX = '/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/';

    public static function isValidShopDomain(?string $shop): bool
    {
        return is_string($shop) && preg_match(self::SHOP_REGEX, $shop) === 1;
    }

    /** Verify the HMAC Shopify appends to app URL query strings. */
    public function validQueryHmac(array $query): bool
    {
        $hmac = $query['hmac'] ?? null;
        if (! $hmac) {
            return false;
        }
        unset($query['hmac'], $query['signature']);
        ksort($query);
        $message = urldecode(http_build_query($query));

        return hash_equals(hash_hmac('sha256', $message, (string) config('shopify.api_secret')), $hmac);
    }

    /** Verify the X-Shopify-Hmac-Sha256 header of a webhook against the raw body. */
    public function validWebhookHmac(string $rawBody, ?string $header): bool
    {
        if (! $header) {
            return false;
        }
        $calculated = base64_encode(hash_hmac('sha256', $rawBody, (string) config('shopify.api_secret'), true));

        return hash_equals($calculated, $header);
    }

    /**
     * Decode and validate an App Bridge session token (HS256 JWT).
     *
     * @return array{shop: string, claims: array}
     */
    public function decodeSessionToken(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed session token');
        }
        [$h, $p, $s] = $parts;

        $expected = $this->b64url(hash_hmac('sha256', "$h.$p", (string) config('shopify.api_secret'), true));
        if (! hash_equals($expected, $s)) {
            throw new RuntimeException('Invalid session token signature');
        }

        $header = json_decode($this->b64urlDecode($h), true);
        $claims = json_decode($this->b64urlDecode($p), true);
        if (! is_array($header) || ! is_array($claims) || ($header['alg'] ?? null) !== 'HS256') {
            throw new RuntimeException('Invalid session token');
        }

        $now = time();
        $leeway = 10;
        if (($claims['exp'] ?? 0) < $now - $leeway || ($claims['nbf'] ?? 0) > $now + $leeway) {
            throw new RuntimeException('Session token expired');
        }
        if (($claims['aud'] ?? null) !== config('shopify.api_key')) {
            throw new RuntimeException('Session token audience mismatch');
        }

        $destHost = parse_url($claims['dest'] ?? '', PHP_URL_HOST);
        $issHost = parse_url($claims['iss'] ?? '', PHP_URL_HOST);
        if (! self::isValidShopDomain($destHost) || $destHost !== $issHost) {
            throw new RuntimeException('Session token shop mismatch');
        }

        return ['shop' => $destHost, 'claims' => $claims];
    }

    /**
     * Managed installation: exchange the session token for an offline Admin API token.
     */
    public function exchangeToken(string $shopDomain, string $sessionToken): array
    {
        $response = Http::asForm()->timeout(15)->post("https://{$shopDomain}/admin/oauth/access_token", [
            'client_id' => config('shopify.api_key'),
            'client_secret' => config('shopify.api_secret'),
            'grant_type' => 'urn:ietf:params:oauth:grant-type:token-exchange',
            'subject_token' => $sessionToken,
            'subject_token_type' => 'urn:ietf:params:oauth:token-type:id_token',
            'requested_token_type' => 'urn:shopify:params:oauth:token-type:offline-access-token',
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Token exchange failed: '.$response->status());
        }

        return $response->json();
    }

    /** Find the shop, install/refresh it via token exchange if needed. */
    public function resolveShop(string $shopDomain, string $sessionToken): Shop
    {
        $shop = Shop::where('domain', $shopDomain)->first();

        if ($shop && $shop->access_token && ! $shop->uninstalled_at) {
            if (! $shop->settings) {
                $this->ensureDefaults($shop);
                $shop->load('settings');
            }

            return $shop;
        }

        $tokenData = $this->exchangeToken($shopDomain, $sessionToken);

        $shop = Shop::updateOrCreate(['domain' => $shopDomain], [
            'access_token' => $tokenData['access_token'],
            'scopes' => $tokenData['scope'] ?? null,
            'installed_at' => now(),
            'uninstalled_at' => null,
        ]);

        $this->ensureDefaults($shop);

        return $shop;
    }

    /** Create the settings row and a starter template the first time a shop is seen. */
    public function ensureDefaults(Shop $shop): void
    {
        $settings = NotificationSetting::firstOrCreate(
            ['shop_id' => $shop->id],
            ['enabled' => false, 'store_name' => ucwords(str_replace('-', ' ', $shop->handle()))]
        );

        if ($shop->templates()->count() === 0) {
            $template = $shop->templates()->create([
                'name' => 'Order confirmation',
                'mode' => MessageTemplate::MODE_TEMPLATE,
                'wa_template_name' => 'order_confirmation',
                'language' => 'en_US',
                'body' => "Hi {{customer_name}}, your order {{order_number}} is confirmed. Total: {{order_total}}",
                'variables' => ['customer_name', 'order_number', 'shop_name', 'order_total'],
            ]);
            $settings->update(['active_template_id' => $template->id]);
        }
    }

    private function b64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private function b64urlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/').str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
