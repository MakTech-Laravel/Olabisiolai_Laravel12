<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesBusinessHours;
use App\Http\Requests\Concerns\ValidatesBusinessSubcategory;
use App\Http\Requests\Concerns\ValidatesSocialAccounts;
use App\Models\Category;
use App\Services\BusinessInfoService;
use App\Support\BusinessSubcategoryResolver;
use App\Services\LocationCatalogService;
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
        $this->normalizeUnsetScalarIds();
        $this->mergeMissingScalarsFromExistingBusiness();
        $this->prepareBusinessHoursFromRequest();
        $this->prepareSocialAccountsFromRequest();
        $this->prepareSubcategoryFromServices();
        $this->mergeMissingSubcategoryFromExistingBusiness();
    }

    protected function normalizeUnsetScalarIds(): void
    {
        foreach (['category_id', 'location_id'] as $field) {
            if ($this->has($field) && (int) $this->input($field) <= 0) {
                $this->request->remove($field);
            }
        }
    }

    protected function mergeMissingScalarsFromExistingBusiness(): void
    {
        $business = $this->resolveTargetBusiness();
        if ($business === null) {
            return;
        }

        $merge = [];

        if (! $this->has('business_name') || trim((string) $this->input('business_name')) === '') {
            $merge['business_name'] = $business->business_name;
        }

        if (! $this->has('business_description') || trim((string) $this->input('business_description')) === '') {
            $merge['business_description'] = $business->business_description;
        }

        if (! $this->has('phone') || trim((string) $this->input('phone')) === '') {
            $merge['phone'] = $business->phone;
        }

        $services = $this->input('services');
        if (! is_array($services) || count(array_filter($services, fn ($service) => is_string($service) && trim($service) !== '')) === 0) {
            $offered = is_array($business->services_offered) ? $business->services_offered : [];
            $merge['services'] = $offered !== [] ? $offered : ['General services'];
        }

        if (! $this->has('category_id') && $business->category_id !== null && (int) $business->category_id > 0) {
            $merge['category_id'] = (int) $business->category_id;
        }

        if (! $this->has('location_id') && $business->location_id !== null && (int) $business->location_id > 0) {
            $merge['location_id'] = (int) $business->location_id;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    protected function resolveTargetBusiness(): ?\App\Models\BusinessInfo
    {
        $user = $this->user('api');
        if ($user === null) {
            return null;
        }

        $businessId = (int) $this->input('business_id', 0);

        return app(BusinessInfoService::class)->findForUser(
            $user,
            $businessId > 0 ? $businessId : null,
        );
    }

    protected function mergeMissingSubcategoryFromExistingBusiness(): void
    {
        if (trim((string) $this->input('subcategory', '')) !== '') {
            return;
        }

        $user = $this->user('api');
        if ($user === null) {
            return;
        }

        $business = app(BusinessInfoService::class)->findForUser(
            $user,
            ($businessId = (int) $this->input('business_id', 0)) > 0 ? $businessId : null,
        );
        if ($business === null || ! is_string($business->subcategory) || trim($business->subcategory) === '') {
            return;
        }

        if (! $this->has('category_id')) {
            return;
        }

        $categoryId = (int) $this->input('category_id');
        if ($categoryId !== (int) $business->category_id) {
            return;
        }

        $this->merge(['subcategory' => trim($business->subcategory)]);
    }

    protected function prepareSubcategoryFromServices(): void
    {
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
     * @return array<string, array<int, File|string|ValidationRule>|string>
     */
    public function rules(): array
    {
        $locationCatalog = app(LocationCatalogService::class);

        return [
            ...$this->businessHoursRules(required: false),
            'location_id' => ['nullable', 'integer', 'min:1', 'exists:locations,id'],
            'category_id' => ['nullable', 'integer', 'min:1', 'exists:categories,id'],
            'business_id' => ['sometimes', 'integer', 'min:1'],
            'subcategory' => ['nullable', 'string', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'full_address' => ['nullable', 'string', 'max:500'],
            'street_address' => ['nullable', 'string', 'max:500'],
            'location_narrative' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'google_place_id' => ['nullable', 'string', 'max:255'],
            'business_description' => ['required', 'string', 'max:10000'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'string', 'max:2048', 'url'],
            ...$this->socialAccountsRules(),
            'logo' => ['nullable', File::image()->max(10 * 1024)],
            'keep_cover_paths' => ['nullable', 'array'],
            'keep_cover_paths.*' => ['required', 'string', 'max:500'],
            'cover_photos' => ['nullable', 'array'],
            'cover_photos.*' => ['required', File::image()->max(10 * 1024)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateBusinessSubcategory($validator, requiredWhenAvailable: true);

        $validator->after(function (Validator $validator): void {
            $keepPaths = $this->input('keep_cover_paths');
            $newPhotos = $this->file('cover_photos', []);
            $hasKeep = is_array($keepPaths);
            $hasNew = is_array($newPhotos) && count($newPhotos) > 0;

            if (! $hasKeep && ! $hasNew) {
                return;
            }

            $user = $this->user('api');
            $businessId = (int) $this->input('business_id', 0);
            $business = $user !== null
                ? app(BusinessInfoService::class)->findForUser($user, $businessId > 0 ? $businessId : null)
                : null;
            $maxPhotos = $business !== null
                ? app(SubscriptionService::class)->maxCoverPhotos($business)
                : app(SubscriptionService::class)->freePhotoLimit();

            $keepCount = $hasKeep ? count($keepPaths) : 0;
            $newCount = $hasNew ? count($newPhotos) : 0;
            $total = $keepCount + $newCount;

            if ($total < 1) {
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
