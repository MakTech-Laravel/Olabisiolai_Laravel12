<?php

namespace App\Enums;

enum ReviewReportReason: string
{
    // Business listing report reasons
    case IllegalOrFraudulent = 'illegal_or_fraudulent';
    case Spam = 'spam';
    case WrongPrice = 'wrong_price';
    case WrongCategory = 'wrong_category';
    case SellerAskedForPrepayment = 'seller_asked_for_prepayment';
    case AlreadySold = 'already_sold';

    // Review report reasons (Flutter + web)
    case Inappropriate = 'inappropriate';
    case NotRelevant = 'not_relevant';
    case ConflictOfInterest = 'conflict_of_interest';

    case Other = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Reasons shown when a customer reports a business listing.
     *
     * @return list<self>
     */
    public static function forBusinessReports(): array
    {
        return [
            self::IllegalOrFraudulent,
            self::Spam,
            self::WrongPrice,
            self::WrongCategory,
            self::SellerAskedForPrepayment,
            self::AlreadySold,
            self::Other,
        ];
    }

    /**
     * Reasons shown when reporting a customer review (Flutter + web).
     *
     * @return list<self>
     */
    public static function forReviewReports(): array
    {
        return [
            self::Spam,
            self::Inappropriate,
            self::NotRelevant,
            self::ConflictOfInterest,
            self::Other,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::IllegalOrFraudulent => 'This is illegal/fraudulent',
            self::Spam => 'This is spam',
            self::WrongPrice => 'The price is wrong',
            self::WrongCategory => 'Wrong category',
            self::SellerAskedForPrepayment => 'Seller asked for prepayment',
            self::AlreadySold => 'It is sold',
            self::Inappropriate => 'Inappropriate',
            self::NotRelevant => 'Not relevant',
            self::ConflictOfInterest => 'Conflict of interest',
            self::Other => 'Other',
        };
    }
}
