<?php

namespace Tests\Feature;

use App\Models\MessageLog;
use App\Models\Shop;
use App\Services\ShopifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['shopify.api_secret' => $this->secret, 'shopify.api_key' => 'test-key']);
    }

    private function shopWithSettings(bool $enabled = true): Shop
    {
        $shop = Shop::create(['domain' => 'demo.myshopify.com', 'access_token' => 'shpat_x', 'installed_at' => now()]);
        app(ShopifyService::class)->ensureDefaults($shop);
        $shop->settings->update(['enabled' => $enabled, 'wa_phone_number_id' => '123456789', 'wa_access_token' => 'EAAT']);

        return $shop->fresh();
    }

    private function post(array $order, ?string $hmac = null)
    {
        $body = json_encode($order);
        $hmac ??= base64_encode(hash_hmac('sha256', $body, $this->secret, true));

        return $this->call('POST', '/webhooks/orders-create', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'demo.myshopify.com',
        ], $body);
    }

    private function order(array $extra = []): array
    {
        return array_merge([
            'id' => 5550001, 'name' => '#1001', 'currency' => 'PKR', 'total_price' => '2500.00',
            'phone' => '+92 300 1234567', 'customer' => ['first_name' => 'Ayesha'],
            'line_items' => [['name' => 'Kurta', 'quantity' => 1]],
        ], $extra);
    }

    public function test_rejects_bad_signature(): void
    {
        $this->shopWithSettings();
        $this->post($this->order(), 'nope')->assertStatus(401);
        $this->assertDatabaseCount('message_logs', 0);
    }

    public function test_sends_whatsapp_template_for_new_order(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]], 200)]);
        $shop = $this->shopWithSettings();

        $this->post($this->order())->assertOk();

        $log = MessageLog::first();
        $this->assertSame(MessageLog::SENT, $log->status);
        $this->assertSame('wamid.ABC', $log->wa_message_id);
        $this->assertSame('923001234567', $log->customer_phone);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), '/123456789/messages')
                && $data['to'] === '923001234567'
                && $data['type'] === 'template'
                && $data['template']['name'] === 'order_confirmation'
                && $data['template']['components'][0]['parameters'][0]['text'] === 'Ayesha';
        });
    }

    public function test_duplicate_delivery_sends_only_once(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]], 200)]);
        $this->shopWithSettings();

        $this->post($this->order())->assertOk();
        $this->post($this->order())->assertOk();

        $this->assertDatabaseCount('message_logs', 1);
        Http::assertSentCount(1);
    }

    public function test_disabled_notifications_are_skipped(): void
    {
        Http::fake();
        $this->shopWithSettings(enabled: false);

        $this->post($this->order())->assertOk();

        $this->assertSame(MessageLog::SKIPPED, MessageLog::first()->status);
        Http::assertNothingSent();
    }

    public function test_order_without_phone_is_skipped(): void
    {
        Http::fake();
        $this->shopWithSettings();

        $this->post($this->order(['phone' => null]))->assertOk();

        $this->assertSame(MessageLog::SKIPPED, MessageLog::first()->status);
    }

    public function test_whatsapp_error_is_recorded(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template not found', 'code' => 132001]], 404)]);
        $this->shopWithSettings();

        $this->post($this->order())->assertOk();

        $log = MessageLog::first();
        $this->assertSame(MessageLog::FAILED, $log->status);
        $this->assertStringContainsString('Template not found', $log->error);
    }
}
