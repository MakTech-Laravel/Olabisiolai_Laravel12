<?php

namespace App\Enums;

enum ReviewReportReason: string
{
    case IllegalOrFraudulent = 'illegal_or_fraudulent';
    case Spam = 'spam';
    case WrongPrice = 'wrong_price';
    case WrongCategory = 'wrong_category';
    case SellerAskedForPrepayment = 'seller_asked_for_prepayment';
    case AlreadySold = 'already_sold';
    case Other = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::IllegalOrFraudulent => 'This is illegal/fraudulent',
            self::Spam => 'This ad is spam',
            self::WrongPrice => 'The price is wrong',
            self::WrongCategory => 'Wrong category',
            self::SellerAskedForPrepayment => 'Seller asked for prepayment',
            self::AlreadySold => 'It is sold',
            self::Other => 'Other',
        };
    }
}
