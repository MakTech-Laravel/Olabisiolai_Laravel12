<?php

namespace App\Enums;

enum MessageReportReason: string
{
    case IllegalContent = 'illegal_content';
    case ScamOrFraud = 'scam_or_fraud';
    case Harassment = 'harassment';
    case HateOrAbuse = 'hate_or_abuse';
    case Spam = 'spam';
    case SexualContent = 'sexual_content';
    case Other = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::IllegalContent => 'Illegal content',
            self::ScamOrFraud => 'Scam or fraud',
            self::Harassment => 'Harassment or threats',
            self::HateOrAbuse => 'Hate or abuse',
            self::Spam => 'Spam',
            self::SexualContent => 'Sexual content',
            self::Other => 'Other',
        };
    }
}
