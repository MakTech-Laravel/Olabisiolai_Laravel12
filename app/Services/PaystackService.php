<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaystackService
{
    private function client(): PendingRequest
    {
        $secret = (string) config('services.paystack.secret', '');
        if ($secret === '') {
            throw new RuntimeException('Paystack secret key is missing. Set PAYSTACK_SECRET_KEY.');
        }

        $baseUrl = rtrim((string) config('services.paystack.base_url', 'https://api.paystack.co'), '/');

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withToken($secret);
    }

    /**
     * Verify a Paystack transaction by reference.
     *
     * @return array<string, mixed>
     */
    public function verify(string $reference): array
    {
        $reference = strtolower(trim($reference));
        if ($reference === '') {
            throw new RuntimeException('Paystack reference is required for verification.');
        }

        $res = $this->client()->get('/transaction/verify/' . urlencode($reference));
        $json = $res->json();

        Log::info('Paystack verification response', ['reference' => $reference]);
        Log::info('Paystack verification response', ['response' => $json]);

        if (! $res->ok() || ! is_array($json) || ($json['status'] ?? false) !== true) {
            $message = is_array($json) ? (string) ($json['message'] ?? 'Paystack verification failed.') : 'Paystack verification failed.';
            if (stripos($message, 'reference not found') !== false) {
                throw new RuntimeException(
                    'Paystack could not find this transaction. Use the reference from your Paystack receipt or bank alert.',
                );
            }
            throw new RuntimeException($message);
        }

        /** @var array<string, mixed> $data */
        $data = is_array($json['data'] ?? null) ? $json['data'] : [];

        return $data;
    }

    public function isValidWebhookSignature(string $payload, ?string $signature): bool
    {
        $signature = trim((string) $signature);
        if ($signature === '') {
            return false;
        }

        $secret = (string) config('services.paystack.secret', '');
        if ($secret === '') {
            return false;
        }

        $computed = hash_hmac('sha512', $payload, $secret);

        return hash_equals($computed, $signature);
    }
}
