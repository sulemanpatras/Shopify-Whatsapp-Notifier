<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\Shop;
use App\Services\MessageRenderer;
use App\Services\PhoneNormalizer;
use App\Services\WhatsAppClient;
use App\Services\WhatsAppException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class SettingsController extends Controller
{
    /** Everything the admin panel needs on first load. */
    public function bootstrap(Request $request): JsonResponse
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');

        return response()->json([
            'settings' => $this->settingsPayload($shop),
            'templates' => $shop->templates()->orderBy('id')->get()->map(fn ($t) => $this->templatePayload($t)),
            'logs' => LogController::recent($shop),
            'stats' => LogController::stats($shop),
            'variables' => MessageRenderer::VARIABLES,
            'sample' => MessageRenderer::sampleVariables($shop->settings),
            'shop' => $shop->domain,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');
        $settings = $shop->settings;

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'store_name' => ['nullable', 'string', 'max:100'],
            'wa_phone_number_id' => ['nullable', 'string', 'regex:/^\d{5,30}$/'],
            'wa_access_token' => ['nullable', 'string', 'max:1000'],
            'clear_token' => ['sometimes', 'boolean'],
            'default_country_code' => ['nullable', 'string', 'regex:/^\d{1,4}$/'],
            'active_template_id' => ['nullable', 'integer', Rule::exists('message_templates', 'id')->where('shop_id', $shop->id)],
        ], [
            'wa_phone_number_id.regex' => 'The phone number ID should contain digits only. Copy it from WhatsApp > API Setup in the Meta developer dashboard.',
            'default_country_code.regex' => 'Enter the country calling code as digits only, for example 92.',
        ]);

        $fill = collect($data)->only(['enabled', 'store_name', 'wa_phone_number_id', 'default_country_code', 'active_template_id'])->all();

        if (! empty($data['wa_access_token'])) {
            $fill['wa_access_token'] = $data['wa_access_token'];
        } elseif (! empty($data['clear_token'])) {
            $fill['wa_access_token'] = null;
        }

        $settings->fill($fill);

        if ($settings->enabled && (! $settings->hasCredentials() || ! $settings->active_template_id)) {
            return response()->json([
                'message' => 'Add your WhatsApp phone number ID, access token and choose a message before turning notifications on.',
                'errors' => ['enabled' => ['Finish the WhatsApp connection and pick a message first.']],
            ], 422);
        }

        $settings->save();

        return response()->json(['settings' => $this->settingsPayload($shop->fresh())]);
    }

    /** Sends the active (or chosen) template to a phone number using sample order data. */
    public function testMessage(Request $request): JsonResponse
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');
        $settings = $shop->settings;

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'template_id' => ['nullable', 'integer', Rule::exists('message_templates', 'id')->where('shop_id', $shop->id)],
        ]);

        $phone = PhoneNormalizer::normalize($data['phone'], $settings->countryCode());
        if (! $phone) {
            return response()->json(['message' => 'That phone number does not look valid. Include the country code, for example +92 300 1234567.'], 422);
        }

        $template = $shop->templates()->find($data['template_id'] ?? $settings->active_template_id);
        if (! $template) {
            return response()->json(['message' => 'Choose a message to test first.'], 422);
        }

        try {
            $vars = MessageRenderer::sampleVariables($settings);
            $client = new WhatsAppClient($settings);

            $id = $template->mode === MessageTemplate::MODE_TEXT
                ? $client->sendText($phone, MessageRenderer::render($template->body, $vars))
                : $client->sendTemplate($phone, $template->wa_template_name, $template->language, MessageRenderer::templateParams($template, $vars));
        } catch (WhatsAppException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not reach WhatsApp. Check your connection and try again.'], 502);
        }

        return response()->json(['message_id' => $id, 'to' => $phone]);
    }

    private function settingsPayload(Shop $shop): array
    {
        $s = $shop->settings;

        return [
            'enabled' => (bool) $s->enabled,
            'store_name' => $s->store_name,
            'wa_phone_number_id' => $s->wa_phone_number_id,
            'has_token' => filled($s->wa_access_token),
            'default_country_code' => $s->default_country_code,
            'fallback_country_code' => config('whatsapp.default_country_code'),
            'active_template_id' => $s->active_template_id,
            'ready' => $s->hasCredentials() && $s->active_template_id !== null,
        ];
    }

    public static function templatePayload(MessageTemplate $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'mode' => $t->mode,
            'wa_template_name' => $t->wa_template_name,
            'language' => $t->language,
            'body' => $t->body,
            'variables' => $t->variables ?? [],
            'meta_body' => MessageRenderer::toMetaBody($t->body),
        ];
    }
}
