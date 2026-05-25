<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'location' => ['required', 'array'],
            'location.country_name' => ['required', 'string', 'max:120'],
            'location.country_iso_code' => ['required', 'string', 'max:10'],
            'location.country_is_active' => ['boolean'],
            'location.country_sort_order' => ['integer', 'min:0'],
            'location.state_name' => ['required', 'string', 'max:120'],
            'location.state_slug' => ['nullable', 'string', 'max:140'],
            'location.city_name' => ['nullable', 'string', 'max:120'],
            'location.lga_name' => ['required', 'string', 'max:120'],
            'location.lga_slug' => ['nullable', 'string', 'max:140', 'unique:locations,lga_slug'],
            'location.vendor_count' => ['integer', 'min:0'],
            'location.google_place_id' => ['nullable', 'string', 'max:255'],
            'location.google_resource_name' => ['nullable', 'string', 'max:255'],
            'location.latitude' => ['required', 'numeric', 'between:-90,90'],
            'location.longitude' => ['required', 'numeric', 'between:-180,180'],
            'location.formatted_address' => ['nullable', 'string', 'max:500'],
            'location.viewport_north' => ['nullable', 'numeric', 'between:-90,90'],
            'location.viewport_south' => ['nullable', 'numeric', 'between:-90,90'],
            'location.viewport_east' => ['nullable', 'numeric', 'between:-180,180'],
            'location.viewport_west' => ['nullable', 'numeric', 'between:-180,180'],
            'location.address_components_json' => ['nullable', 'array'],

            'map_pick' => ['nullable', 'array'],
            'map_pick.placeId' => ['nullable', 'string', 'max:255'],
            'map_pick.resourceName' => ['nullable', 'string', 'max:255'],
            'map_pick.formattedAddress' => ['nullable', 'string', 'max:500'],
            'map_pick.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'map_pick.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'map_pick.viewport' => ['nullable', 'array'],
            'map_pick.viewport.north' => ['nullable', 'numeric', 'between:-90,90'],
            'map_pick.viewport.south' => ['nullable', 'numeric', 'between:-90,90'],
            'map_pick.viewport.east' => ['nullable', 'numeric', 'between:-180,180'],
            'map_pick.viewport.west' => ['nullable', 'numeric', 'between:-180,180'],
            'map_pick.addressComponents' => ['nullable', 'array'],

            'boost_config' => ['nullable', 'array'],
            'boost_config.enabled' => ['boolean'],
            'boost_config.tiers' => ['nullable', 'array'],
            'boost_config.tiers.*.key' => ['required_with:boost_config.tiers', 'string', 'max:30'],
            'boost_config.tiers.*.label' => ['required_with:boost_config.tiers', 'string', 'max:60'],
            'boost_config.tiers.*.total_slots' => ['required_with:boost_config.tiers', 'integer', 'min:0'],
            'boost_config.tiers.*.price_amount' => ['nullable', 'numeric', 'min:0'],
            'boost_config.tiers.*.durations' => ['nullable', 'array'],
            'boost_config.tiers.*.durations.*.days' => ['required_with:boost_config.tiers.*.durations', 'integer', 'in:7,14,30'],
            'boost_config.tiers.*.durations.*.enabled' => ['required_with:boost_config.tiers.*.durations', 'boolean'],
            'boost_config.tiers.*.durations.*.price_amount' => ['required_with:boost_config.tiers.*.durations', 'numeric', 'min:0'],
            'boost_config.durations' => ['nullable', 'array'],
            'boost_config.durations.*.days' => ['required_with:boost_config.durations', 'integer', 'in:7,14,30'],
            'boost_config.durations.*.enabled' => ['required_with:boost_config.durations', 'boolean'],
            'boost_config.durations.*.price_amount' => ['required_with:boost_config.durations', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $location = $this->input('location', []);

        if (is_array($location)) {
            if (empty($location['state_slug']) && ! empty($location['state_name'])) {
                $location['state_slug'] = strtolower(str_replace(' ', '-', $location['state_name']));
            }

            if (empty($location['lga_slug']) && ! empty($location['lga_name'])) {
                $location['lga_slug'] = strtolower(str_replace(' ', '-', $location['lga_name']));
            }

            $this->merge(['location' => $location]);
        }
    }
}
