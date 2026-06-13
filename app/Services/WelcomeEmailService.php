<?php

namespace App\Services;

use App\Mail\WelcomeCustomerMail;
use App\Mail\WelcomeVendorMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WelcomeEmailService
{
    public function sendAfterRegistration(User $user): void
    {
        if (! filled($user->email)) {
            return;
        }

        $mailable = $user->role === 'vendor'
            ? new WelcomeVendorMail($user)
            : new WelcomeCustomerMail($user);

        $this->sendWithRetry($user, $mailable, $user->role === 'vendor' ? 'vendor' : 'customer');
    }

    private function sendWithRetry(User $user, object $mailable, string $type): void
    {
        $attempts = 0;
        $maxAttempts = 2;

        while ($attempts < $maxAttempts) {
            $attempts++;

            try {
                Mail::to($user->email)->send($mailable);

                return;
            } catch (\Throwable $throwable) {
                Log::error('Welcome email delivery failed.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'type' => $type,
                    'attempt' => $attempts,
                    'error' => $throwable->getMessage(),
                ]);

                if ($attempts >= $maxAttempts) {
                    return;
                }

                usleep(500_000);
            }
        }
    }
}
