<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;

class SimulateOrder extends Command
{
    protected $signature = 'shopify:simulate-order {shop : my-store.myshopify.com} {--phone=+923001234567} {--id=}';

    protected $description = 'Send a signed fake orders/create webhook through the app to test the WhatsApp flow.';

    public function handle(): int
    {
        $id = (int) ($this->option('id') ?: random_int(100000, 999999));
        $body = json_encode([
            'id' => $id,
            'name' => '#'.($id % 10000),
            'currency' => 'PKR',
            'total_price' => '3450.00',
            'phone' => $this->option('phone'),
            'customer' => ['first_name' => 'Test', 'last_name' => 'Customer'],
            'line_items' => [['name' => 'Sample product', 'quantity' => 1]],
        ]);

        $hmac = base64_encode(hash_hmac('sha256', $body, (string) config('shopify.api_secret'), true));

        $request = Request::create('/webhooks/orders-create', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
            'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $this->argument('shop'),
            'HTTP_X_SHOPIFY_TOPIC' => 'orders/create',
        ], $body);

        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response); // runs the after-response send

        $this->line($response->getStatusCode().' '.$response->getContent());
        $this->info('Check the Message log in the admin panel.');

        return $response->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
