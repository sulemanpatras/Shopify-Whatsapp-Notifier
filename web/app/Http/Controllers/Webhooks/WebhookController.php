<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\SendOrderWhatsAppNotification;
use App\Models\MessageLog;
use App\Models\Shop;
use App\Services\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /** orders/create: queue one WhatsApp confirmation per order. */
    public function ordersCreate(Request $request): JsonResponse
    {
        $domain = (string) $request->header('X-Shopify-Shop-Domain');
        $shop = Shop::where('domain', $domain)->first();
        $order = $request->json()->all();

        if (! $shop || empty($order['id'])) {
            return response()->json(['ok' => true, 'ignored' => 'unknown shop or empty payload']);
        }

        $settings = $shop->settings;

        // Shopify can deliver the same webhook more than once: one log row per order.
        $log = MessageLog::firstOrNew(['shop_id' => $shop->id, 'order_id' => $order['id']]);
        if ($log->exists) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        $phone = $settings
            ? PhoneNormalizer::fromOrder($order, $settings->countryCode())
            : null;

        $log->fill([
            'order_name' => $order['name'] ?? null,
            'customer_phone' => $phone,
            'template_id' => $settings?->active_template_id,
            'order_payload' => $this->trim($order),
        ]);

        if (! $settings || ! $settings->enabled) {
            $log->fill(['status' => MessageLog::SKIPPED, 'error' => 'Notifications are turned off.'])->save();
        } elseif (! $phone) {
            $log->fill(['status' => MessageLog::SKIPPED, 'error' => 'The order has no valid phone number.'])->save();
        } else {
            $log->fill(['status' => MessageLog::PENDING])->save();
            // Runs after the 200 response has been sent, well inside Shopify's 5 second limit.
            SendOrderWhatsAppNotification::dispatch($log->id)->afterResponse();
        }

        return response()->json(['ok' => true]);
    }

    public function appUninstalled(Request $request): JsonResponse
    {
        Shop::where('domain', (string) $request->header('X-Shopify-Shop-Domain'))
            ->update(['access_token' => null, 'uninstalled_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /** Mandatory privacy webhooks. */
    public function compliance(Request $request): JsonResponse
    {
        $topic = $request->header('X-Shopify-Topic');
        $payload = $request->json()->all();
        $shop = Shop::where('domain', $payload['shop_domain'] ?? $request->header('X-Shopify-Shop-Domain'))->first();

        if ($shop) {
            match ($topic) {
                // We only keep phone numbers/order snapshots in message logs.
                'customers/redact' => $shop->logs()
                    ->whereIn('order_id', $payload['orders_to_redact'] ?? [])
                    ->delete(),
                'shop/redact' => $shop->delete(),
                default => null, // customers/data_request: nothing to export beyond the logs the merchant already sees
            };
        }

        return response()->json(['ok' => true]);
    }

    /** Keep only what is needed to render/resend a message. */
    private function trim(array $o): array
    {
        return [
            'id' => $o['id'] ?? null,
            'name' => $o['name'] ?? null,
            'currency' => $o['currency'] ?? null,
            'total_price' => $o['total_price'] ?? null,
            'order_status_url' => $o['order_status_url'] ?? null,
            'customer' => [
                'first_name' => $o['customer']['first_name'] ?? null,
                'last_name' => $o['customer']['last_name'] ?? null,
            ],
            'shipping_address' => ['first_name' => $o['shipping_address']['first_name'] ?? null, 'last_name' => $o['shipping_address']['last_name'] ?? null],
            'billing_address' => ['first_name' => $o['billing_address']['first_name'] ?? null, 'last_name' => $o['billing_address']['last_name'] ?? null],
            'line_items' => collect($o['line_items'] ?? [])->map(fn ($i) => ['name' => $i['name'] ?? $i['title'] ?? 'Item', 'quantity' => $i['quantity'] ?? 1])->all(),
        ];
    }
}
