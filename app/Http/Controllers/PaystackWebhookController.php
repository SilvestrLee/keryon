<?php

namespace App\Http\Controllers;

use App\Billing\Providers\Paystack\PaystackWebhookProcessor;
use App\Billing\Providers\ProviderConfigurationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaystackWebhookProcessor $processor): JsonResponse
    {
        try {
            $result = $processor->process($request->getContent(), $request->header('x-paystack-signature'), $request->ip());
        } catch (ProviderConfigurationException) {
            return response()->json(['accepted' => false], 503);
        }

        return response()->json(['accepted' => $result['accepted']], $result['status']);
    }
}
