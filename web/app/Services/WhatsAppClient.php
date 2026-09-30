<?php

namespace App\Services;

use App\Models\NotificationSetting;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the WhatsApp Business Cloud API (Meta Graph API).
 */
class WhatsAppClient
{
    public function __construct(private NotificationSetting $settings)
    {
    }

    /** Send an approved message template. $params are the ordered body variables ({{1}}, {{2}}...). */
    public function sendTemplate(string $to, string $templateName, string $language, array $params = []): string
    {
        $template = [
            'name' => $templateName,
            'language' => ['code' => $language],
        ];

        if ($params) {
            $template['components'] = [[
                'type' => 'body',
                'parameters' => array_map(
                    fn ($value) => ['type' => 'text', 'text' => $this->clean((string) $value)],
                    array_values($params)
                ),
            ]];
        }

        return $this->post([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => $template,
        ]);
    }

    /** Free-form text. WhatsApp only delivers it inside the 24h customer-care window (or to test numbers). */
    public function sendText(string $to, string $body): string
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $body],
        ]);
    }

    private function post(array $payload): string
    {
        if (! $this->settings->hasCredentials()) {
            throw new WhatsAppException('WhatsApp credentials are missing. Add the phone number ID and access token in Settings.');
        }

        $url = sprintf(
            '%s/%s/%s/messages',
            config('whatsapp.base_url'),
            config('whatsapp.graph_version'),
            $this->settings->wa_phone_number_id
        );

        $response = Http::withToken($this->settings->wa_access_token)
            ->timeout(config('whatsapp.timeout'))
            ->acceptJson()
            ->post($url, $payload);

        if (! $response->successful()) {
            $err = $response->json('error') ?? [];
            $message = trim(($err['message'] ?? 'HTTP '.$response->status())
                .(isset($err['error_data']['details']) ? ' — '.$err['error_data']['details'] : '')
                .(isset($err['code']) ? " (code {$err['code']})" : ''));
            throw new WhatsAppException($message);
        }

        return (string) ($response->json('messages.0.id') ?? '');
    }

    /** Template parameters can't contain newlines, tabs or 4+ consecutive spaces. */
    private function clean(string $value): string
    {
        $value = preg_replace('/[\r\n\t]+/', ' ', $value);
        $value = preg_replace('/ {4,}/', '   ', $value);

        return trim($value) === '' ? '-' : trim($value);
    }
}
