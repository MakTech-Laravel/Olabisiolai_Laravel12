<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesBusinessHours;
use App\Http\Requests\Concerns\ValidatesBusinessSubcategory;
use App\Http\Requests\Concerns\ValidatesSocialAccounts;
use App\Services\LocationCatalogService;
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
    }

    /**
     * @return array<string, array<int, File|string|ValidationRule>|string>
     */
    public function rules(): array
    {
        $locationCatalog = app(LocationCatalogService::class);

        return [
            ...$this->businessHoursRules(required: false),
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'subcategory' => ['nullable', 'string', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'full_address' => ['nullable', 'string', 'max:500'],
            'street_address' => ['nullable', 'string', 'max:500'],
            'business_description' => ['required', 'string', 'max:10000'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'string', 'max:2048', 'url'],
            ...$this->socialAccountsRules(),
            'logo' => ['nullable', File::image()->max(10 * 1024)],
            'keep_cover_paths' => ['nullable', 'array', 'max:5'],
            'keep_cover_paths.*' => ['required', 'string', 'max:500'],
            'cover_photos' => ['nullable', 'array', 'max:5'],
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

            $keepCount = $hasKeep ? count($keepPaths) : 0;
            $newCount = $hasNew ? count($newPhotos) : 0;
            $total = $keepCount + $newCount;

            if ($total < 1) {
                $validator->errors()->add('cover_photos', 'Please keep or upload at least one gallery photo.');
            }

            if ($total > 5) {
                $validator->errors()->add('cover_photos', 'You can have up to 5 gallery photos.');
            }
        });
    }
}
