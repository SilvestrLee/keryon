<?php

use App\Http\Controllers\PaystackWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/billing/webhooks/paystack', PaystackWebhookController::class)
    ->name('billing.webhooks.paystack');
