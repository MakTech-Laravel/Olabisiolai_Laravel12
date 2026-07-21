<?php

namespace App\Http\Requests\Concerns;

use App\Enums\DayOfWeek;
use Illuminate\Validation\Rule;

trait ValidatesBusinessHours
{
    /**
     * @return array<string, array<int, mixed>|string>
     */
    protected function businessHoursRules(bool $required = true): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'business_hours' => [$presence, 'array', 'min:1', 'max:7'],
            'business_hours.*.day' => ['required', 'string', Rule::in(DayOfWeek::values())],
            'business_hours.*.is_closed' => ['required', 'boolean'],
            'business_hours.*.is_24_hours' => ['sometimes', 'boolean'],
            'business_hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'business_hours.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    protected function prepareBusinessHoursFromRequest(): void
    {
        $hours = $this->input('business_hours');

        if (! is_array($hours)) {
            return;
        }

        $normalized = [];

        foreach ($hours as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $normalized[] = [
                'day' => strtolower((string) ($entry['day'] ?? '')),
                'is_closed' => filter_var($entry['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'is_24_hours' => filter_var($entry['is_24_hours'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'opens_at' => $entry['opens_at'] ?? $entry['opening_time'] ?? null,
                'closes_at' => $entry['closes_at'] ?? $entry['closing_time'] ?? null,
            ];
        }

        $this->merge(['business_hours' => $normalized]);
    }
}
