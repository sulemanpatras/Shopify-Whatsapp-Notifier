<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use App\Models\Shop;
use App\Services\MessageRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $data = $this->validated($request);

        $template = $shop->templates()->create($data);

        return response()->json(['template' => SettingsController::templatePayload($template)], 201);
    }

    public function update(Request $request, int $template): JsonResponse
    {
        $model = $this->shop($request)->templates()->findOrFail($template);
        $model->update($this->validated($request));

        return response()->json(['template' => SettingsController::templatePayload($model->fresh())]);
    }

    public function destroy(Request $request, int $template): JsonResponse
    {
        $shop = $this->shop($request);
        $model = $shop->templates()->findOrFail($template);

        if ($shop->settings->active_template_id === $model->id) {
            return response()->json(['message' => 'This message is currently in use. Choose a different active message in Settings first.'], 422);
        }

        $model->delete();

        return response()->json(['deleted' => true]);
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'mode' => ['required', 'in:template,text'],
            'wa_template_name' => ['required_if:mode,template', 'nullable', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'language' => ['required', 'string', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?$/'],
            'body' => ['required', 'string', 'max:1024'],
        ], [
            'wa_template_name.regex' => 'WhatsApp template names use lowercase letters, numbers and underscores only, for example order_confirmation.',
            'wa_template_name.required_if' => 'Enter the template name exactly as it appears in WhatsApp Manager.',
            'language.regex' => 'Use a WhatsApp language code such as en, en_US or ur.',
        ]);

        $used = MessageRenderer::extractVariables($data['body']);
        $unknown = array_values(array_diff($used, array_keys(MessageRenderer::VARIABLES)));
        if ($unknown) {
            abort(response()->json([
                'message' => 'Unknown variable: {{'.$unknown[0].'}}. Use the variable buttons to insert valid ones.',
                'errors' => ['body' => ['Unknown variable {{'.$unknown[0].'}}.']],
            ], 422));
        }

        $data['variables'] = $used;
        if ($data['mode'] === MessageTemplate::MODE_TEXT) {
            $data['wa_template_name'] = null;
        }

        return $data;
    }
}
