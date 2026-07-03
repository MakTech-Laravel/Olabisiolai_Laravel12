<?php

namespace App\Services;

use App\Models\BusinessInfo;
use App\Models\ReferralCode;
use App\Models\ReferralInvite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ReferralService
{
    private const CREDIT_AMOUNT = 1000.0;

    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function getOrCreateCode(User $user): ReferralCode
    {
        $existing = ReferralCode::query()->where('user_id', $user->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $base = Str::slug(Str::before($user->email ?? $user->name ?? 'user', '@'));
        $base = Str::upper(Str::substr(preg_replace('/[^a-z0-9]/i', '', $base) ?: 'GID', 0, 8));
        $code = $base.Str::upper(Str::random(4));

        while (ReferralCode::query()->where('code', $code)->exists()) {
            $code = $base.Str::upper(Str::random(4));
        }

        return ReferralCode::query()->create([
            'user_id' => $user->id,
            'code' => $code,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function referralsPayload(User $user): array
    {
        $code = $this->getOrCreateCode($user);
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $link = "{$frontendUrl}/register?ref={$code->code}";

        $invites = ReferralInvite::query()
            ->where('referrer_user_id', $user->id)
            ->with('invitee:id,name,email')
            ->latest()
            ->limit(50)
            ->get();

        $totalEarned = (float) ReferralInvite::query()
            ->where('referrer_user_id', $user->id)
            ->where('status', 'paid')
            ->sum('credited_amount');

        return [
            'code' => $code->code,
            'referral_link' => $link,
            'total_earned' => $totalEarned,
            'invites' => $invites->map(function (ReferralInvite $invite): array {
                return [
                    'id' => $invite->id,
                    'invitee_name' => $invite->invitee?->name,
                    'invitee_email' => $invite->invitee_email ?? $invite->invitee?->email,
                    'status' => $invite->status,
                    'credited_amount' => $invite->credited_amount !== null ? (float) $invite->credited_amount : null,
                    'created_at' => $invite->created_at?->toIso8601String(),
                ];
            })->all(),
        ];
    }

    public function attachReferralOnRegister(User $invitee, ?string $rawCode): void
    {
        Log::info('Attaching referral on register', ['invitee_id' => $invitee->id, 'raw_code' => $rawCode]);
        $code = Str::upper(trim((string) $rawCode));
        if ($code === '') {
            return;
        }

        $referralCode = ReferralCode::query()->where('code', $code)->first();
        if ($referralCode === null) {
            return;
        }

        if ($referralCode->user_id === $invitee->id) {
            return;
        }

        if (ReferralInvite::query()->where('invitee_user_id', $invitee->id)->exists()) {
            return;
        }

        ReferralInvite::query()->create([
            'referrer_user_id' => $referralCode->user_id,
            'invitee_user_id' => $invitee->id,
            'code' => $code,
            'status' => 'joined',
            'invitee_email' => $invitee->email,
        ]);
    }

    public function onVerificationApproved(BusinessInfo $business): void
    {
        $invitee = $business->user;
        if ($invitee === null) {
            return;
        }

        $invite = ReferralInvite::query()
            ->where('invitee_user_id', $invitee->id)
            ->whereIn('status', ['pending', 'joined', 'verified'])
            ->first();

        if ($invite === null) {
            return;
        }

        if ($invite->referrer_user_id === $invitee->id) {
            return;
        }

        if ($invite->status === 'paid') {
            return;
        }

        DB::transaction(function () use ($invitee, $invite, $business): void {
            $locked = ReferralInvite::query()->whereKey($invite->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status === 'paid') {
                return;
            }

            $referrer = User::query()->find($locked->referrer_user_id);
            if ($referrer === null) {
                throw new RuntimeException('Referrer not found.');
            }

            $this->walletService->credit(
                $referrer,
                self::CREDIT_AMOUNT,
                'Referral reward',
                'referral_'.$locked->id,
                ['invitee_user_id' => $locked->invitee_user_id, 'business_info_id' => $business->id],
            );

            $this->walletService->credit(
                $invitee,
                self::CREDIT_AMOUNT,
                'Referral welcome reward',
                'referral_invitee_'.$locked->id,
                ['referrer_user_id' => $locked->referrer_user_id, 'business_info_id' => $business->id],
            );

            $locked->update([
                'status' => 'paid',
                'credited_amount' => self::CREDIT_AMOUNT,
                'credited_at' => now(),
            ]);
        });
    }
}
