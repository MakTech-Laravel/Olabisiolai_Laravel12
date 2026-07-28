<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\BusinessCatalogItem;
use App\Models\BusinessInfo;
use App\Models\Review;
use App\Models\User;
use InvalidArgumentException;

enum UploadableType: string
{
    case Product = 'product';
    case Review = 'review';
    case Profile = 'profile';
    case Business = 'business';

    /**
     * @return class-string
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Product => BusinessCatalogItem::class,
            self::Review => Review::class,
            self::Profile => User::class,
            self::Business => BusinessInfo::class,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromKey(string $key): self
    {
        $type = self::tryFrom($key);

        if ($type === null) {
            throw new InvalidArgumentException("Unknown uploadable type key [{$key}].");
        }

        return $type;
    }
}
