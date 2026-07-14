<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Webhook\OrderWebhookController;
use App\Http\Controllers\Webhook\StoreWebhookController;
use App\Http\Controllers\Admin\LanguageController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/lang-import', [LanguageController::class, 'langImport']);

Route::get('/ping', function () {
    return app()->currentLocale();
});

Route::prefix('webhook')->group(function () {

    Route::post('/shopify/created', [OrderWebhookController::class, 'orderCreated']);

    // WooCommerce
    Route::post('/woocommerce/created', [OrderWebhookController::class, 'orderCreated']);

    // Magento
    Route::post('/magento/created', [OrderWebhookController::class, 'orderCreated']);

    // OpenCart
    Route::post('/opencart/created', [OrderWebhookController::class, 'orderCreated']);

    // PrestaShop
    Route::post('/prestashop/created', [OrderWebhookController::class, 'orderCreated']);

    // Custom/Other platforms
    Route::post('/{platform}/created', [OrderWebhookController::class, 'orderCreated']);
    //ayor
    Route::post('/ayor/created', [OrderWebhookController::class, 'orderCreated']);
    //lightfunnels
    Route::post('/lightfunnels/created', [OrderWebhookController::class, 'orderCreated'])
        ->name('webhook.lightfunnels.created');

    Route::post('/foorweb/created', [OrderWebhookController::class, 'orderCreated'])
        ->name('webhook.foorweb.created');

    Route::post('/googlesheet/created', [OrderWebhookController::class, 'orderCreated'])
        ->name('webhook.googlesheet.created');
});
