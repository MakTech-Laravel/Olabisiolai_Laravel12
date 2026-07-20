<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\WhatsAppServiceInterface;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class WhatsAppService implements WhatsAppServiceInterface
{
    public function isConfigured(): bool
    {
        if (! filter_var(config('services.whatsapp.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return trim((string) config('services.whatsapp.api_key')) !== '';
    }

    public function resolveDestination(User $vendor): ?string
    {
        $vendor->loadMissing('businessInfo');

        $businessWhatsapp = trim((string) ($vendor->businessInfo?->whatsapp ?? ''));

        if ($businessWhatsapp !== '') {
            return $businessWhatsapp;
        }

        $phone = trim((string) $vendor->phone);

        return $phone !== '' ? $phone : null;
    }

    public function sendNewMessageAlert(User $vendor, string $senderName, string $actionUrl): bool
    {
        $destination = $this->resolveDestination($vendor);
        $normalizedPhone = $destination !== null ? PhoneNormalizer::normalize($destination) : null;

        if ($normalizedPhone === null) {
            Log::warning('WhatsApp alert skipped: no valid destination phone.', [
                'user_id' => $vendor->id,
            ]);

            return false;
        }

        if (! $this->isConfigured()) {
            Log::info('WhatsApp not configured. Away-message alert logged for development.', [
                'user_id' => $vendor->id,
                'phone' => PhoneNormalizer::mask($normalizedPhone),
                'sender_name' => $senderName,
                'action_url' => $actionUrl,
            ]);

            return true;
        }

        return match ((string) config('services.whatsapp.driver', 'termii')) {
            'termii' => $this->sendViaTermiiTemplate($normalizedPhone, $senderName, $actionUrl),
            default => $this->logUnsupportedDriver($normalizedPhone),
        };
    }

    private function sendViaTermiiTemplate(string $phone, string $senderName, string $actionUrl): bool
    {
        $deviceId = trim((string) config('services.whatsapp.sender_id'));
        $templateId = trim((string) config('services.whatsapp.template_id'));

        if ($deviceId === '' || $templateId === '') {
            Log::warning('WhatsApp alert skipped: device_id or template_id missing.', [
                'phone' => PhoneNormalizer::mask($phone),
            ]);

            return false;
        }

        $senderKey = (string) config('services.whatsapp.template_sender_key', '1');
        $urlKey = (string) config('services.whatsapp.template_url_key', '2');

        try {
            $response = Http::timeout((int) config('services.whatsapp.timeout', 15))
                ->acceptJson()
                ->post($this->endpoint('/api/send/template'), [
                    'api_key' => config('services.whatsapp.api_key'),
                    'phone_number' => $phone,
                    'device_id' => $deviceId,
                    'template_id' => $templateId,
                    'data' => [
                        $senderKey => $senderName,
                        $urlKey => $actionUrl,
                    ],
                ]);

            $response->throw();

            return true;
        } catch (RequestException $exception) {
            Log::error('WhatsApp away-message alert delivery failed.', [
                'phone' => PhoneNormalizer::mask($phone),
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json(),
                'base_url' => config('services.whatsapp.base_url'),
                'template_id' => $templateId,
            ]);

            return false;
        }
    }

    private function logUnsupportedDriver(string $phone): bool
    {
        Log::warning('WhatsApp alert skipped: unsupported driver.', [
            'driver' => config('services.whatsapp.driver'),
            'phone' => PhoneNormalizer::mask($phone),
        ]);

        return false;
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.whatsapp.base_url'), '/').$path;
    }
}
