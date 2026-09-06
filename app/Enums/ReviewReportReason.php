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
    case AbusiveOrOffensive = 'abusive_or_offensive';
    case FakeOrMisleading = 'fake_or_misleading';
    case Harassment = 'harassment';
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
     * Reasons shown when reporting a customer review.
     *
     * @return list<self>
     */
    public static function forReviewReports(): array
    {
        return [
            self::AbusiveOrOffensive,
            self::FakeOrMisleading,
            self::Spam,
            self::Harassment,
            self::IllegalOrFraudulent,
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
            self::AbusiveOrOffensive => 'Abusive or offensive language',
            self::FakeOrMisleading => 'Fake or misleading review',
            self::Harassment => 'Harassment or personal attack',
            self::Other => 'Other',
        };
    }
}
