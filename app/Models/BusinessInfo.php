<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use App\Enums\VerificationStatus;
use Database\Factories\BusinessInfoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BusinessInfo extends Model
{
    /** @use HasFactory<BusinessInfoFactory> */
    use HasFactory;

    protected $table = 'business_info';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sort_order',
        'location_id',
        'user_id',
        'category_id',
        'subcategory',
        'business_name',
        'street_address',
        'latitude',
        'longitude',
        'google_place_id',
        'business_description',
        'services_offered',
        'phone',
        'whatsapp',
        'website',
        'social_accounts',
        'logo_path',
        'cover_photo_paths',
        'verification_status',
        'is_flagged',
        'business_status',
        'verified_by',
        'verified_at',
        'verification_note',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'services_offered' => 'array',
            'social_accounts' => 'array',
            'cover_photo_paths' => 'array',
            'is_flagged' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'verified_at' => 'datetime',
            'verification_status' => VerificationStatus::class,
            'business_status' => BusinessStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasOne<BusinessSubscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(BusinessSubscription::class);
    }

    /**
     * @return HasOne<Boost, $this>
     */
    public function boost(): HasOne
    {
        return $this->hasOne(Boost::class);
    }

    /**
     * @return HasMany<BoostPurchaseRequest, $this>
     */
    public function boostPurchaseRequests(): HasMany
    {
        return $this->hasMany(BoostPurchaseRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return HasMany<AdminVendorMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AdminVendorMessage::class);
    }

    /**
     * @return HasMany<VerificationDocument, $this>
     */
    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(VerificationDocument::class);
    }

    /**
     * @return HasMany<VerificationNote, $this>
     */
    public function verificationNotes(): HasMany
    {
        return $this->hasMany(VerificationNote::class);
    }

    /**
     * @return HasMany<VerificationWorkflow, $this>
     */
    public function verificationWorkflows(): HasMany
    {
        return $this->hasMany(VerificationWorkflow::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'business_id');
    }

    /**
     * @return HasMany<BusinessCatalogItem, $this>
     */
    public function catalogItems(): HasMany
    {
        return $this->hasMany(BusinessCatalogItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<Favorite, $this>
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class, 'business_info_id');
    }

    /**
     * Users following this business owner account.
     *
     * @return HasMany<UserFollow, $this>
     */
    public function followerLinks(): HasMany
    {
        return $this->hasMany(UserFollow::class, 'business_info_id');
    }

    /**
     * @return HasMany<BusinessHour, $this>
     */
    public function businessHours(): HasMany
    {
        return $this->hasMany(BusinessHour::class);
    }
}
