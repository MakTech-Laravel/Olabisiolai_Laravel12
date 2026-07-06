<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\PaymentReconciliationService;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PaystackWebhookController extends Controller
{
    public function __construct(
        private readonly PaystackService $paystackService,
        private readonly PaymentReconciliationService $paymentReconciliation,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('x-paystack-signature');

        if (! $this->paystackService->isValidWebhookSignature($payload, $signature)) {
            return response()->json(['message' => 'Invalid signature.'], Response::HTTP_UNAUTHORIZED);
        }

        $event = json_decode($payload, true);
        if (! is_array($event)) {
            return response()->json(['message' => 'Invalid payload.'], Response::HTTP_BAD_REQUEST);
        }

        $eventType = (string) ($event['event'] ?? '');
        if ($eventType !== 'charge.success') {
            return response()->json(['message' => 'Ignored.']);
        }

        $data = is_array($event['data'] ?? null) ? $event['data'] : [];
        $reference = trim((string) ($data['reference'] ?? ''));
        if ($reference === '') {
            return response()->json(['message' => 'Missing reference.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->paymentReconciliation->reconcilePaystackReference($reference);

            return response()->json([
                'message' => 'Reconciled.',
                'activated' => $result['activated'],
                'payment_id' => $result['payment']->id,
            ]);
        } catch (RuntimeException $exception) {
            Log::warning('paystack.webhook.reconcile_failed', [
                'reference' => $reference,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json(['message' => 'Webhook processing failed.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
