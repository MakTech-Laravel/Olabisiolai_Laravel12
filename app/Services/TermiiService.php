<?php

namespace App\Services;

use App\Support\PhoneNormalizer;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TermiiService
{
    public function isConfigured(): bool
    {
        $apiKey = (string) config('services.termii.api_key');

        return $apiKey !== '';
    }

    /**
     * @return bool True when the SMS provider accepted the message (or dev log fallback was used).
     */
    public function sendOtp(string $phone, string $code, string $context = 'verification'): bool
    {
        $normalizedPhone = PhoneNormalizer::normalize($phone);

        if (! $normalizedPhone) {
            Log::warning('Termii OTP skipped: invalid phone number.', [
                'phone' => PhoneNormalizer::mask($phone),
                'context' => $context,
            ]);

            return false;
        }

        if (! $this->isConfigured()) {
            Log::info('Termii not configured. OTP logged for development.', [
                'phone' => PhoneNormalizer::mask($normalizedPhone),
                'context' => $context,
                'otp' => $code,
            ]);

            return true;
        }

        $message = $this->buildMessage($code, $context);

        try {
            $response = Http::timeout((int) config('services.termii.timeout', 15))
                ->acceptJson()
                ->post($this->endpoint('/api/sms/send'), [
                    'api_key' => config('services.termii.api_key'),
                    'to' => $normalizedPhone,
                    'from' => config('services.termii.sender_id'),
                    'sms' => $message,
                    'type' => 'plain',
                    'channel' => config('services.termii.channel', 'dnd'),
                ]);

            $response->throw();

            return true;
        } catch (RequestException $exception) {
            Log::error('Termii OTP delivery failed.', [
                'phone' => PhoneNormalizer::mask($normalizedPhone),
                'context' => $context,
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json(),
                'base_url' => config('services.termii.base_url'),
                'sender_id' => config('services.termii.sender_id'),
                'channel' => config('services.termii.channel', 'dnd'),
            ]);

            if (app()->environment('local', 'testing')) {
                Log::info('DEV OTP (Termii SMS failed — use this code to test).', [
                    'phone' => PhoneNormalizer::mask($normalizedPhone),
                    'context' => $context,
                    'otp' => $code,
                ]);
            }

            return false;
        }
    }

    /**
     * Must match the message format approved on the Termii account (N-Alert / DND).
     * Example: "Your Gidira OTP is 482910. Do not share with anyone."
     */
    private function buildMessage(string $code, string $_context): string
    {
        $brand = (string) config('services.termii.otp_brand', 'Gidira');

        return "Your {$brand} OTP is {$code}. Do not share with anyone.";
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.termii.base_url'), '/') . $path;
    }
}
