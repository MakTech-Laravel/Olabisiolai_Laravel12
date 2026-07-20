<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use App\Traits\HasMessaging;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    public const DEFAULT_AVATAR_URL_PATH = '/images/avatar/default-header-avatar.png';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasMessaging, Notifiable;

    /**
     * @var list<string>
     */
    protected $appends = [
        'image_url',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'email',
        'phone',
        'image',
        'location',
        'role',
        'status',
        'wants_marketing_emails',
        'settings',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (User $user): void {
            $user->uuid = Str::upper(Str::random(10));
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'wants_marketing_emails' => 'boolean',
            'settings' => 'array',
            'status' => UserStatus::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasEnabledTwoFactorAuthentication(): bool
    {
        return $this->two_factor_confirmed_at !== null
            && filled($this->two_factor_secret);
    }

    public function isAccountVerified(): bool
    {
        return $this->email_verified_at !== null || $this->phone_verified_at !== null;
    }

    public function hasUnverifiedEmail(): bool
    {
        return filled($this->email) && $this->email_verified_at === null;
    }

    public function canMakePurchases(): bool
    {
        return ! $this->hasUnverifiedEmail();
    }

    public function registrationVerificationChannel(): ?string
    {
        $settings = is_array($this->settings) ? $this->settings : [];
        $channel = $settings['registration_verification_channel'] ?? null;

        return is_string($channel) && in_array($channel, ['email', 'phone'], true)
            ? $channel
            : null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isVendor(): bool
    {
        return $this->role === 'vendor';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function notificationPreference(string $channel, bool $default = true): bool
    {
        $settings = is_array($this->settings) ? $this->settings : [];
        $notifications = $settings['notifications'] ?? null;
        if (! is_array($notifications) || ! array_key_exists($channel, $notifications)) {
            return $default;
        }

        return filter_var($notifications[$channel], FILTER_VALIDATE_BOOLEAN);
    }

    public function wantsPushNotifications(): bool
    {
        return $this->notificationPreference('push', true);
    }

    public function wantsEmailNotifications(): bool
    {
        return $this->notificationPreference('email', true);
    }

    public function wantsSmsNotifications(): bool
    {
        return $this->notificationPreference('sms', false);
    }

    public function wantsWhatsappNotifications(): bool
    {
        return $this->notificationPreference('whatsapp', true);
    }

    /**
     * Public profile image URL (uploaded file or default avatar).
     */
    public function getImageUrlAttribute(): string
    {
        if (filled($this->image)) {
            $stored = storage_url((string) $this->image);

            if ($stored !== null && $stored !== '') {
                return $stored;
            }
        }

        return url(self::DEFAULT_AVATAR_URL_PATH);
    }

    /**
     * Primary business (lowest sort_order, then id) for backward compatibility.
     *
     * @return HasOne<BusinessInfo, $this>
     */
    public function businessInfo(): HasOne
    {
        return $this->hasOne(BusinessInfo::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<BusinessInfo, $this>
     */
    public function businessInfos(): HasMany
    {
        return $this->hasMany(BusinessInfo::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<Favorite, $this>
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Saved checkout profiles (billing details + optional masked card metadata). Never stores full PAN/CVV.
     *
     * @return HasMany<VendorPaymentMethod, $this>
     */
    public function vendorPaymentMethods(): HasMany
    {
        return $this->hasMany(VendorPaymentMethod::class);
    }

    /**
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * @return HasMany<UserFollow, $this>
     */
    public function followingLinks(): HasMany
    {
        return $this->hasMany(UserFollow::class, 'follower_id');
    }

    /**
     * @return HasMany<UserFollow, $this>
     */
    public function followerLinks(): HasMany
    {
        return $this->hasMany(UserFollow::class, 'following_id');
    }
}
