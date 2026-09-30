<?php

namespace App\Services;

use App\Models\MessageTemplate;
use App\Models\NotificationSetting;

class MessageRenderer
{
    /** Variables merchants can use in templates: key => description. */
    public const VARIABLES = [
        'customer_name' => 'Customer first name',
        'customer_full_name' => 'Customer full name',
        'order_number' => 'Order number, e.g. #1001',
        'order_total' => 'Total with currency, e.g. PKR 2,500.00',
        'order_currency' => 'Currency code',
        'items_summary' => 'Items, e.g. 2 x Shirt, 1 x Cap',
        'items_count' => 'Number of items',
        'shop_name' => 'Store name from Settings',
        'order_status_url' => 'Order status page link',
    ];

    /** Build the variable values from an orders/create payload. */
    public static function variablesFromOrder(array $order, NotificationSetting $settings): array
    {
        $first = $order['customer']['first_name']
            ?? $order['shipping_address']['first_name']
            ?? $order['billing_address']['first_name']
            ?? null;

        $full = trim(implode(' ', array_filter([
            $order['customer']['first_name'] ?? $order['shipping_address']['first_name'] ?? null,
            $order['customer']['last_name'] ?? $order['shipping_address']['last_name'] ?? null,
        ])));

        $currency = $order['currency'] ?? $order['presentment_currency'] ?? '';
        $amount = number_format((float) ($order['total_price'] ?? 0), 2);

        $items = collect($order['line_items'] ?? []);
        $summary = $items->map(fn ($i) => ($i['quantity'] ?? 1).' x '.($i['name'] ?? $i['title'] ?? 'Item'))->implode(', ');

        return [
            'customer_name' => $first ?: 'there',
            'customer_full_name' => $full ?: ($first ?: 'there'),
            'order_number' => $order['name'] ?? ('#'.($order['order_number'] ?? $order['id'] ?? '')),
            'order_total' => trim("$currency $amount"),
            'order_currency' => $currency,
            'items_summary' => $summary !== '' ? $summary : '-',
            'items_count' => (string) $items->sum(fn ($i) => (int) ($i['quantity'] ?? 1)),
            'shop_name' => $settings->store_name ?: ($settings->shop?->handle() ?? ''),
            'order_status_url' => $order['order_status_url'] ?? '',
        ];
    }

    /** Replace {{tokens}} in a body with values (unknown tokens are left untouched). */
    public static function render(string $body, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', function ($m) use ($vars) {
            return $vars[$m[1]] ?? $m[0];
        }, $body);
    }

    /** Ordered parameter values for a Meta template ({{1}}, {{2}}...). */
    public static function templateParams(MessageTemplate $template, array $vars): array
    {
        return collect($template->variables ?? [])
            ->map(fn ($key) => (string) ($vars[$key] ?? ''))
            ->all();
    }

    /** Sample order used for previews and test messages. */
    public static function sampleVariables(NotificationSetting $settings): array
    {
        return self::variablesFromOrder([
            'name' => '#1001',
            'currency' => 'PKR',
            'total_price' => '2500.00',
            'customer' => ['first_name' => 'Ayesha', 'last_name' => 'Khan'],
            'line_items' => [['name' => 'Cotton Kurta - M', 'quantity' => 2], ['name' => 'Scarf', 'quantity' => 1]],
            'order_status_url' => 'https://example.myshopify.com/orders/sample',
        ], $settings);
    }

    /** Distinct variable keys in order of first appearance, e.g. ["customer_name", "order_number"]. */
    public static function extractVariables(string $body): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/i', $body, $m);

        return array_values(array_unique(array_map('strtolower', $m[1])));
    }

    /** The body as it must be entered in WhatsApp Manager: {{customer_name}} -> {{1}}, {{order_number}} -> {{2}}... */
    public static function toMetaBody(string $body): string
    {
        $order = self::extractVariables($body);

        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', function ($m) use ($order) {
            $i = array_search(strtolower($m[1]), $order, true);

            return '{{'.($i + 1).'}}';
        }, $body);
    }
}
