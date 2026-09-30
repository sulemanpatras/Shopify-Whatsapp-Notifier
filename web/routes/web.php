<?php

use App\Http\Controllers\Admin\AppController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Webhooks\WebhookController;
use Illuminate\Support\Facades\Route;

// Embedded admin panel shell (loaded inside the Shopify admin iframe).
Route::get('/', [AppController::class, 'index'])->middleware('shopify.frame');

// JSON API used by the admin panel. Every call carries a Shopify session token.
Route::prefix('api/admin')->middleware('shopify.session')->group(function () {
    Route::get('bootstrap', [SettingsController::class, 'bootstrap']);
    Route::put('settings', [SettingsController::class, 'update']);
    Route::post('test-message', [SettingsController::class, 'testMessage'])->middleware('throttle:10,1');

    Route::post('templates', [TemplateController::class, 'store']);
    Route::put('templates/{template}', [TemplateController::class, 'update']);
    Route::delete('templates/{template}', [TemplateController::class, 'destroy']);

    Route::get('logs', [LogController::class, 'index']);
    Route::post('logs/{log}/resend', [LogController::class, 'resend'])->middleware('throttle:20,1');
});

// Shopify webhooks (HMAC verified).
Route::prefix('webhooks')->middleware('shopify.webhook')->group(function () {
    Route::post('orders-create', [WebhookController::class, 'ordersCreate']);
    Route::post('app-uninstalled', [WebhookController::class, 'appUninstalled']);
    Route::post('compliance', [WebhookController::class, 'compliance']);
});
