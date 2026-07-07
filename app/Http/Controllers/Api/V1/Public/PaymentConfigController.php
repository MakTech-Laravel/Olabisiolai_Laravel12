<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

class PaymentConfigController extends Controller
{
    public function show()
    {
        return sendResponse(true, 'Payment configuration retrieved successfully.', [
            'paystack_public_key' => (string) config('services.paystack.public', ''),
            'flutterwave_public_key' => (string) config('services.flutterwave.public', ''),
        ], Response::HTTP_OK);
    }
}
