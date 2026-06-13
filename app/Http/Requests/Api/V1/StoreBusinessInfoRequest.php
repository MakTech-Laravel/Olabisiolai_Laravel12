<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesBusinessHours;
use App\Http\Requests\Concerns\ValidatesBusinessSubcategory;
use App\Http\Requests\Concerns\ValidatesSocialAccounts;
use App\Rules\NigerianPhoneNumber;
use App\Services\LocationCatalogService;
use App\Support\PhoneNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StoreBusinessInfoRequest extends FormRequest
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

        foreach (['phone', 'whatsapp'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && trim($value) !== '') {
                $this->merge([
                    $field => PhoneNormalizer::normalize($value) ?? trim($value),
                ]);
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
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'subcategory' => ['nullable', 'string', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'full_address' => ['nullable', 'string', 'max:500'],
            'street_address' => ['nullable', 'string', 'max:500'],
            'business_description' => ['required', 'string', 'max:10000'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', new NigerianPhoneNumber()],
            'whatsapp' => ['nullable', 'string', new NigerianPhoneNumber()],
            'website' => ['nullable', 'string', 'max:2048', 'url'],
            ...$this->socialAccountsRules(),
            'logo' => ['required', File::image()->max(10 * 1024)],
            'cover_photos' => ['required', 'array', 'min:1', 'max:5'],
            'cover_photos.*' => ['required', File::image()->max(10 * 1024)],
            'subscription_plan' => ['nullable', 'string', Rule::in(['free', 'premium'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateBusinessSubcategory($validator, requiredWhenAvailable: true);
    }
}
