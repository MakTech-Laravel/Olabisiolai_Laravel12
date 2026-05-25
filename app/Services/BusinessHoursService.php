<?php

namespace App\Services;

use App\Enums\DayOfWeek;
use App\Models\BusinessHour;
use App\Models\BusinessInfo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BusinessHoursService
{
    /**
     * @return list<array{day: string, is_closed: bool, opens_at: string|null, closes_at: string|null}>
     */
    public function defaultSchedule(): array
    {
        return [
            ['day' => DayOfWeek::Monday->value, 'is_closed' => false, 'opens_at' => '08:00', 'closes_at' => '19:00'],
            ['day' => DayOfWeek::Tuesday->value, 'is_closed' => false, 'opens_at' => '08:00', 'closes_at' => '19:00'],
            ['day' => DayOfWeek::Wednesday->value, 'is_closed' => false, 'opens_at' => '08:00', 'closes_at' => '19:00'],
            ['day' => DayOfWeek::Thursday->value, 'is_closed' => false, 'opens_at' => '08:00', 'closes_at' => '19:00'],
            ['day' => DayOfWeek::Friday->value, 'is_closed' => false, 'opens_at' => '08:00', 'closes_at' => '19:00'],
            ['day' => DayOfWeek::Saturday->value, 'is_closed' => false, 'opens_at' => '09:00', 'closes_at' => '16:00'],
            ['day' => DayOfWeek::Sunday->value, 'is_closed' => true, 'opens_at' => null, 'closes_at' => null],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $rawHours
     * @return list<array{day: string, is_closed: bool, opens_at: string|null, closes_at: string|null}>
     */
    public function normalizeInput(?array $rawHours): array
    {
        if ($rawHours === null || $rawHours === []) {
            return $this->defaultSchedule();
        }

        $byDay = [];

        foreach ($rawHours as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $day = strtolower(trim((string) ($entry['day'] ?? '')));
            if (! in_array($day, DayOfWeek::values(), true)) {
                continue;
            }

            $isClosed = filter_var($entry['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $opensAt = $this->normalizeTimeValue($entry['opens_at'] ?? $entry['opening_time'] ?? null);
            $closesAt = $this->normalizeTimeValue($entry['closes_at'] ?? $entry['closing_time'] ?? null);

            if ($isClosed) {
                $opensAt = null;
                $closesAt = null;
            }

            $byDay[$day] = [
                'day' => $day,
                'is_closed' => $isClosed,
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
            ];
        }

        $normalized = [];

        foreach (DayOfWeek::ordered() as $dayEnum) {
            $day = $dayEnum->value;
            $normalized[] = $byDay[$day] ?? [
                'day' => $day,
                'is_closed' => true,
                'opens_at' => null,
                'closes_at' => null,
            ];
        }

        $this->assertValidSchedule($normalized);

        return $normalized;
    }

    /**
     * @param  list<array{day: string, is_closed: bool, opens_at: string|null, closes_at: string|null}>  $schedule
     */
    public function syncForBusiness(BusinessInfo $business, array $schedule): void
    {
        $business->businessHours()->delete();

        foreach ($schedule as $entry) {
            BusinessHour::query()->create([
                'business_info_id' => $business->id,
                'day' => $entry['day'],
                'opening_time' => $entry['is_closed'] ? null : $entry['opens_at'],
                'closing_time' => $entry['is_closed'] ? null : $entry['closes_at'],
                'is_closed' => $entry['is_closed'],
            ]);
        }
    }

    public function seedDefaultsForBusiness(BusinessInfo $business): void
    {
        if ($business->businessHours()->exists()) {
            return;
        }

        $this->syncForBusiness($business, $this->defaultSchedule());
    }

    /**
     * Demo schedules for seeders — rotates by business id for variety.
     *
     * @return list<array{day: string, is_closed: bool, opens_at: string|null, closes_at: string|null}>
     */
    public function demoScheduleForBusiness(BusinessInfo $business): array
    {
        $variants = $this->demoScheduleVariants();

        return $variants[max(0, ($business->id - 1) % count($variants))];
    }

    /**
     * @return list<list<array{day: string, is_closed: bool, opens_at: string|null, closes_at: string|null}>>
     */
    public function demoScheduleVariants(): array
    {
        return [
            $this->defaultSchedule(),
            [
                ['day' => DayOfWeek::Monday->value, 'is_closed' => false, 'opens_at' => '09:00', 'closes_at' => '18:00'],
                ['day' => DayOfWeek::Tuesday->value, 'is_closed' => false, 'opens_at' => '09:00', 'closes_at' => '18:00'],
                ['day' => DayOfWeek::Wednesday->value, 'is_closed' => false, 'opens_at' => '09:00', 'closes_at' => '18:00'],
                ['day' => DayOfWeek::Thursday->value, 'is_closed' => false, 'opens_at' => '09:00', 'closes_at' => '18:00'],
                ['day' => DayOfWeek::Friday->value, 'is_closed' => false, 'opens_at' => '09:00', 'closes_at' => '18:00'],
                ['day' => DayOfWeek::Saturday->value, 'is_closed' => false, 'opens_at' => '10:00', 'closes_at' => '14:00'],
                ['day' => DayOfWeek::Sunday->value, 'is_closed' => true, 'opens_at' => null, 'closes_at' => null],
            ],
            [
                ['day' => DayOfWeek::Monday->value, 'is_closed' => false, 'opens_at' => '10:00', 'closes_at' => '20:00'],
                ['day' => DayOfWeek::Tuesday->value, 'is_closed' => false, 'opens_at' => '10:00', 'closes_at' => '20:00'],
                ['day' => DayOfWeek::Wednesday->value, 'is_closed' => false, 'opens_at' => '10:00', 'closes_at' => '20:00'],
                ['day' => DayOfWeek::Thursday->value, 'is_closed' => false, 'opens_at' => '10:00', 'closes_at' => '20:00'],
                ['day' => DayOfWeek::Friday->value, 'is_closed' => false, 'opens_at' => '10:00', 'closes_at' => '21:00'],
                ['day' => DayOfWeek::Saturday->value, 'is_closed' => false, 'opens_at' => '11:00', 'closes_at' => '21:00'],
                ['day' => DayOfWeek::Sunday->value, 'is_closed' => false, 'opens_at' => '12:00', 'closes_at' => '17:00'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serializeForBusiness(BusinessInfo $business): array
    {
        $hours = $this->orderedHoursCollection($business);

        if ($hours->isEmpty()) {
            return array_map(
                fn(array $entry): array => $this->formatHourEntryFromArray($entry),
                $this->defaultSchedule(),
            );
        }

        return $hours
            ->map(fn(BusinessHour $hour): array => $this->formatHourEntry($hour))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildDisplayGroups(BusinessInfo $business): array
    {
        $serialized = $this->serializeForBusiness($business);

        if ($serialized === []) {
            return [];
        }

        $groups = [];
        $current = null;

        foreach ($serialized as $entry) {
            $signature = $this->scheduleSignature($entry);

            if ($current === null || $current['signature'] !== $signature) {
                if ($current !== null) {
                    $groups[] = $this->finalizeDisplayGroup($current);
                }

                $current = [
                    'start' => $entry,
                    'end' => $entry,
                    'signature' => $signature,
                ];

                continue;
            }

            $current['end'] = $entry;
        }

        if ($current !== null) {
            $groups[] = $this->finalizeDisplayGroup($current);
        }

        return $groups;
    }

    /**
     * @param  list<array{day: string, is_closed: bool, opens_at: string|null, closes_at: string|null}>  $schedule
     */
    private function assertValidSchedule(array $schedule): void
    {
        $errors = [];

        foreach ($schedule as $index => $entry) {
            $dayLabel = DayOfWeek::tryFrom($entry['day'])?->label() ?? $entry['day'];

            if ($entry['is_closed']) {
                continue;
            }

            if ($entry['opens_at'] === null || $entry['closes_at'] === null) {
                $errors["business_hours.{$index}.opens_at"] = "Opening and closing times are required for {$dayLabel} when not closed.";

                continue;
            }

            $opens = Carbon::createFromFormat('H:i', $entry['opens_at']);
            $closes = Carbon::createFromFormat('H:i', $entry['closes_at']);

            if ($closes->lte($opens)) {
                $errors["business_hours.{$index}.closes_at"] = "Closing time must be after opening time for {$dayLabel}.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function normalizeTimeValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);

        foreach (['H:i', 'H:i:s', 'g:i A', 'g:i a', 'h:i A', 'h:i a'] as $format) {
            try {
                return Carbon::createFromFormat($format, $raw)->format('H:i');
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($raw)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return Collection<int, BusinessHour>
     */
    private function orderedHoursCollection(BusinessInfo $business): Collection
    {
        $hours = $business->relationLoaded('businessHours')
            ? $business->businessHours
            : $business->businessHours()->get();

        $order = array_flip(DayOfWeek::values());

        return $hours->sortBy(fn(BusinessHour $hour): int => $order[$hour->day->value] ?? 99)->values();
    }

    /**
     * @param  array{day: string, is_closed: bool, opens_at: string|null, closes_at: string|null}  $entry
     * @return array<string, mixed>
     */
    private function formatHourEntryFromArray(array $entry): array
    {
        $day = DayOfWeek::from($entry['day']);

        return [
            'day' => $day->value,
            'day_label' => $day->label(),
            'day_short' => $day->shortLabel(),
            'is_closed' => $entry['is_closed'],
            'opens_at' => $entry['opens_at'],
            'closes_at' => $entry['closes_at'],
            'opens_at_formatted' => $this->formatTimeForDisplay($entry['opens_at']),
            'closes_at_formatted' => $this->formatTimeForDisplay($entry['closes_at']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatHourEntry(BusinessHour $hour): array
    {
        $opensAt = $this->extractTimeValue($hour->opening_time);
        $closesAt = $this->extractTimeValue($hour->closing_time);

        return [
            'day' => $hour->day->value,
            'day_label' => $hour->day->label(),
            'day_short' => $hour->day->shortLabel(),
            'is_closed' => (bool) $hour->is_closed,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'opens_at_formatted' => $this->formatTimeForDisplay($opensAt),
            'closes_at_formatted' => $this->formatTimeForDisplay($closesAt),
        ];
    }

    private function extractTimeValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);

        if (preg_match('/(\d{1,2}):(\d{2})/', $raw, $matches) === 1) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return null;
    }

    private function formatTimeForDisplay(?string $time): ?string
    {
        $normalized = $this->extractTimeValue($time);

        if ($normalized === null) {
            return null;
        }

        return Carbon::createFromFormat('H:i', $normalized)->format('h:i A');
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function scheduleSignature(array $entry): string
    {
        if ($entry['is_closed']) {
            return 'closed';
        }

        return ($entry['opens_at'] ?? '') . '|' . ($entry['closes_at'] ?? '');
    }

    /**
     * @param  array{start: array<string, mixed>, end: array<string, mixed>, signature: string}  $group
     * @return array<string, mixed>
     */
    private function finalizeDisplayGroup(array $group): array
    {
        $start = $group['start'];
        $end = $group['end'];
        $isClosed = (bool) ($start['is_closed'] ?? false);

        $label = $start['day_short'] === $end['day_short']
            ? $start['day_label']
            : $start['day_short'] . ' — ' . $end['day_short'];

        return [
            'label' => $label,
            'is_closed' => $isClosed,
            'opens_at' => $start['opens_at'] ?? null,
            'closes_at' => $start['closes_at'] ?? null,
            'opens_at_formatted' => $start['opens_at_formatted'] ?? null,
            'closes_at_formatted' => $start['closes_at_formatted'] ?? null,
        ];
    }
}
