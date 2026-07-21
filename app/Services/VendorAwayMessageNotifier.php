<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\WhatsAppServiceInterface;
use App\Mail\NewGidiraMessageMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class VendorAwayMessageNotifier
{
    public function __construct(
        private readonly TermiiService $termii,
        private readonly WhatsAppServiceInterface $whatsapp,
    ) {}

    /**
     * Notify-only outbound channels (no message body). Channels are independent.
     */
    public function notify(User $vendor, string $senderName, string $actionUrl): void
    {
        $absoluteUrl = $this->absoluteActionUrl($actionUrl);

        if ($vendor->wantsEmailNotifications()) {
            $this->sendEmail($vendor, $senderName, $absoluteUrl);
        }

        if ($vendor->wantsSmsNotifications()) {
            $this->sendSms($vendor, $senderName, $absoluteUrl);
        }

        if ($vendor->wantsWhatsappNotifications()) {
            $this->sendWhatsapp($vendor, $senderName, $absoluteUrl);
        }
    }

    private function sendEmail(User $vendor, string $senderName, string $actionUrl): void
    {
        $email = trim((string) $vendor->email);

        if ($email === '') {
            return;
        }

        try {
            Mail::to($email)->send(new NewGidiraMessageMail($vendor, $senderName, $actionUrl));
        } catch (Throwable $exception) {
            Log::error('Vendor away-message email failed.', [
                'user_id' => $vendor->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function sendSms(User $vendor, string $senderName, string $actionUrl): void
    {
        $phone = trim((string) $vendor->phone);

        if ($phone === '') {
            return;
        }

        $sms = sprintf(
            'You have a new Gidira message from %s. Open: %s',
            $senderName,
            $actionUrl,
        );

        try {
            $this->termii->sendAlert($phone, $sms);
        } catch (Throwable $exception) {
            Log::error('Vendor away-message SMS failed.', [
                'user_id' => $vendor->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function sendWhatsapp(User $vendor, string $senderName, string $actionUrl): void
    {
        try {
            $this->whatsapp->sendNewMessageAlert($vendor, $senderName, $actionUrl);
        } catch (Throwable $exception) {
            Log::error('Vendor away-message WhatsApp failed.', [
                'user_id' => $vendor->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function absoluteActionUrl(string $actionUrl): string
    {
        if (str_starts_with($actionUrl, 'http://') || str_starts_with($actionUrl, 'https://')) {
            return $actionUrl;
        }

        $base = rtrim((string) config('messaging.away_alert_app_url', config('app.url')), '/');
        $path = str_starts_with($actionUrl, '/') ? $actionUrl : '/'.$actionUrl;

        return $base.$path;
    }
}
