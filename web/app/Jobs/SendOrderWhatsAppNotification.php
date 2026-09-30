<?php

namespace App\Jobs;

use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Services\MessageRenderer;
use App\Services\WhatsAppClient;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

/**
 * Sends the WhatsApp confirmation for one MessageLog row.
 *
 * It is dispatched with ->afterResponse() from the webhook so Shopify gets its 200 immediately.
 * To move sending onto a real queue worker, make this class implement ShouldQueue and dispatch it normally.
 */
class SendOrderWhatsAppNotification
{
    use Dispatchable;

    public function __construct(public int $logId)
    {
    }

    public function handle(): void
    {
        $log = MessageLog::with('shop.settings')->find($this->logId);
        if (! $log || ! $log->shop) {
            return;
        }

        $settings = $log->shop->settings;
        $template = $log->template ?? $settings?->activeTemplate;

        if (! $settings || ! $template) {
            $log->update(['status' => MessageLog::FAILED, 'error' => 'No active message template selected.']);

            return;
        }

        $log->increment('attempts');

        try {
            $vars = MessageRenderer::variablesFromOrder($log->order_payload ?? [], $settings);
            $client = new WhatsAppClient($settings);

            $messageId = $template->mode === MessageTemplate::MODE_TEXT
                ? $client->sendText($log->customer_phone, MessageRenderer::render($template->body, $vars))
                : $client->sendTemplate(
                    $log->customer_phone,
                    $template->wa_template_name,
                    $template->language,
                    MessageRenderer::templateParams($template, $vars)
                );

            $log->update([
                'status' => MessageLog::SENT,
                'wa_message_id' => $messageId,
                'template_id' => $template->id,
                'error' => null,
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
            $log->update(['status' => MessageLog::FAILED, 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }
}
