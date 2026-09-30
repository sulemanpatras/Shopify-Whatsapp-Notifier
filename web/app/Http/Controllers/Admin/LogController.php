<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendOrderWhatsAppNotification;
use App\Models\MessageLog;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shop = $request->attributes->get('shop');

        return response()->json([
            'logs' => self::recent($shop, $request->query('status')),
            'stats' => self::stats($shop),
        ]);
    }

    public function resend(Request $request, int $log): JsonResponse
    {
        /** @var Shop $shop */
        $shop = $request->attributes->get('shop');
        $model = $shop->logs()->findOrFail($log);

        if (blank($model->customer_phone)) {
            return response()->json(['message' => 'This order has no phone number to message.'], 422);
        }
        if (! $shop->settings->hasCredentials()) {
            return response()->json(['message' => 'Add your WhatsApp credentials in Settings first.'], 422);
        }

        $model->update(['status' => MessageLog::PENDING, 'error' => null]);
        SendOrderWhatsAppNotification::dispatchSync($model->id);

        return response()->json([
            'log' => self::format($model->fresh()),
            'stats' => self::stats($shop),
        ]);
    }

    public static function recent(Shop $shop, ?string $status = null): array
    {
        return $shop->logs()
            ->when($status && in_array($status, ['sent', 'failed', 'skipped', 'pending'], true), fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn ($l) => self::format($l))
            ->all();
    }

    public static function stats(Shop $shop): array
    {
        $counts = $shop->logs()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'sent' => (int) ($counts['sent'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
            'skipped' => (int) ($counts['skipped'] ?? 0),
            'pending' => (int) ($counts['pending'] ?? 0),
        ];
    }

    public static function format(MessageLog $l): array
    {
        return [
            'id' => $l->id,
            'order_name' => $l->order_name ?: '#'.$l->order_id,
            'phone' => $l->customer_phone,
            'status' => $l->status,
            'error' => $l->error,
            'attempts' => $l->attempts,
            'sent_at' => optional($l->sent_at)->toIso8601String(),
            'created_at' => $l->created_at->toIso8601String(),
        ];
    }
}
