<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesBusinessHours;
use App\Http\Requests\Concerns\ValidatesBusinessSubcategory;
use App\Http\Requests\Concerns\ValidatesSocialAccounts;
use App\Models\Category;
use App\Services\BusinessInfoService;
use App\Support\BusinessSubcategoryResolver;
use App\Services\SubscriptionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class UpdateBusinessInfoRequest extends FormRequest
{
    use ValidatesBusinessHours;
    use ValidatesBusinessSubcategory;
    use ValidatesSocialAccounts;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareBusinessHoursFromRequest();
        $this->prepareSocialAccountsFromRequest();
        $this->prepareSubcategoryFromServices();
        $this->normalizeOptionalForeignKeys();
        $this->normalizeClearableStrings();
    }

    protected function normalizeOptionalForeignKeys(): void
    {
        $merge = [];

        foreach (['category_id', 'location_id'] as $key) {
            if (! $this->exists($key)) {
                continue;
            }

            $value = $this->input($key);
            if ($value === null || $value === '') {
                $merge[$key] = null;

                continue;
            }

            if ((int) $value <= 0) {
                $merge[$key] = null;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * Empty strings on clearable fields become null so they pass validation and clear DB values.
     */
    protected function normalizeClearableStrings(): void
    {
        $merge = [];

        foreach (['website', 'whatsapp', 'google_place_id', 'street_address', 'full_address'] as $key) {
            if (! $this->exists($key)) {
                continue;
            }

            $value = $this->input($key);
            if (is_string($value) && trim($value) === '') {
                $merge[$key] = null;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * Only invent a subcategory when the client is changing category (and left subcategory blank).
     */
    protected function prepareSubcategoryFromServices(): void
    {
        if (! $this->exists('category_id')) {
            return;
        }

        $subcategory = trim((string) $this->input('subcategory', ''));
        $categoryId = $this->input('category_id');

        if ($subcategory !== '' || $categoryId === null || $categoryId === '') {
            return;
        }

        $services = $this->input('services', []);
        if (! is_array($services)) {
            $services = [];
        }

        $resolved = BusinessSubcategoryResolver::resolve(
            null,
            (int) $categoryId,
            array_values(array_filter($services, fn ($service) => is_string($service))),
        );

        if ($resolved !== null) {
            $this->merge(['subcategory' => $resolved]);

            return;
        }

        $category = Category::query()->find((int) $categoryId, ['id', 'subcategories']);
        if ($category === null) {
            return;
        }

        $allowed = is_array($category->subcategories) ? $category->subcategories : [];
        foreach ($allowed as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $this->merge(['subcategory' => trim($candidate)]);

                break;
            }
        }
    }

    /**
     * Logo/covers may be uploaded as multipart files; MediaUploadService stores media + queues optimize.
     *
     * @return array<string, array<int, File|string|ValidationRule>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->businessHoursRules(required: false),
            'business_id' => ['sometimes', 'integer', 'min:1'],
            'location_id' => ['sometimes', 'nullable', 'integer', 'exists:locations,id'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'subcategory' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'full_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'street_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'google_place_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_description' => ['sometimes', 'nullable', 'string', 'max:150'],
            'services' => ['sometimes', 'nullable', 'array', 'min:1'],
            'services.*' => ['nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:30'],
            'website' => ['sometimes', 'nullable', 'string', 'max:2048', 'url'],
            ...$this->socialAccountsRules(),
            'logo' => ['sometimes', 'nullable', File::types(['jpg', 'jpeg', 'png', 'webp'])->max(10 * 1024)],
            'keep_cover_paths' => ['sometimes', 'nullable', 'array'],
            'keep_cover_paths.*' => ['nullable', 'string', 'max:500'],
            'cover_photos' => ['sometimes', 'nullable', 'array'],
            'cover_photos.*' => ['nullable', File::types(['jpg', 'jpeg', 'png', 'webp'])->max(10 * 1024)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo' => 'Logo must be a JPG, PNG, or WebP image (max 10MB). Apple HEIC is not supported.',
            'cover_photos.*.*' => 'Each cover photo must be a JPG, PNG, or WebP image (max 10MB). Apple HEIC is not supported.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $touchingCategoryOrSubcategory = $this->exists('category_id') || $this->exists('subcategory');

        if ($touchingCategoryOrSubcategory) {
            $this->validateBusinessSubcategory(
                $validator,
                requiredWhenAvailable: $this->exists('category_id'),
            );
        }

        $validator->after(function (Validator $validator): void {
            $hasKeep = $this->exists('keep_cover_paths');
            $newPhotos = $this->file('cover_photos', []);
            $hasNew = is_array($newPhotos) && count($newPhotos) > 0;

            if (! $hasKeep && ! $hasNew) {
                return;
            }

            $keepPaths = $hasKeep ? ($this->input('keep_cover_paths') ?? []) : [];
            if (! is_array($keepPaths)) {
                $keepPaths = [];
            }

            $user = $this->user('api');
            $businessId = (int) $this->input('business_id', 0);
            $business = null;
            if ($user !== null) {
                $business = $businessId > 0
                    ? app(BusinessInfoService::class)->findForUser($user, $businessId)
                    : app(BusinessInfoService::class)->findForUser($user);
            }

            $maxPhotos = $business !== null
                ? app(SubscriptionService::class)->maxCoverPhotos($business)
                : app(SubscriptionService::class)->freePhotoLimit();

            $keepCount = count(array_filter($keepPaths, fn ($path) => is_string($path) && trim($path) !== ''));
            $newCount = $hasNew ? count($newPhotos) : 0;
            $total = ($hasKeep ? $keepCount : 0) + $newCount;

            if ($hasKeep && $total < 1) {
                $validator->errors()->add('cover_photos', 'Please keep or upload at least one gallery photo.');
            }

            if ($total > $maxPhotos) {
                $validator->errors()->add(
                    'cover_photos',
                    "You can have up to {$maxPhotos} gallery photos on your current plan.",
                );
            }
        });
    }
}
