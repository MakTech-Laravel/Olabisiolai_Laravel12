<?php

namespace App\Services;

use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ReviewReplyService
{
    /**
     * Store a new reply for a review.
     */
    public function storeReply(array $data, User $vendor): ReviewReply
    {
        return DB::transaction(function () use ($data, $vendor): ReviewReply {
            $review = Review::findOrFail($data['review_id']);
            
            // Verify that vendor owns business being reviewed
            $this->verifyVendorOwnership($review, $vendor);

            // Check if vendor already replied to this review
            $existingReply = ReviewReply::where('review_id', $data['review_id'])
                ->where('vendor_user_id', $vendor->id)
                ->first();

            if ($existingReply) {
                abort(422, 'You have already replied to this review. You can only provide one reply per review.');
            }

            return ReviewReply::create([
                'review_id' => $data['review_id'],
                'vendor_user_id' => $vendor->id,
                'reply_text' => trim($data['reply_text']),
            ])->load(['vendor:id,first_name,last_name,email']);
        });
    }

    /**
     * Update an existing reply.
     */
    public function updateReply(ReviewReply $reply, array $data, User $vendor): ReviewReply
    {
        // Verify that the vendor owns this reply
        if ($reply->vendor_user_id !== $vendor->id) {
            abort(403, 'Unauthorized to update this reply');
        }

        $reply->update([
            'reply_text' => trim($data['reply_text']),
        ]);

        return $reply->load(['vendor:id,first_name,last_name,email']);
    }

   

    /**
     * Get replies for a specific review.
     */
    public function getReviewReplies(Review $review): Collection
    {
        return $review->replies()
            ->with(['vendor:id,first_name,last_name,email'])
            ->oldest('created_at')
            ->get();
    }

    /**
     * Get all reviews for a vendor's business with their replies.
     */
    public function getVendorBusinessReviews(User $vendor, array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;

        // Get the vendor's business
        $business = $vendor->businessInfo;
        if (! $business) {
            abort(404, 'Business not found for this vendor');
        }

        $query = Review::with([
            'images',
            'user:id,first_name,last_name,email',
            'replies' => function ($query) {
                $query->with(['vendor:id,first_name,last_name,email'])->oldest('created_at');
            }
        ])
        ->where('business_id', $business->id)
        ->latest('created_at');

        // Apply filters
        if (isset($filters['rating'])) {
            $query->byRating((int) $filters['rating']);
        }

        if (isset($filters['has_reply'])) {
            if ($filters['has_reply']) {
                $query->whereHas('replies');
            } else {
                $query->whereDoesntHave('replies');
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('review_text', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get vendor's review statistics.
     */
    public function getVendorReviewStats(User $vendor): array
    {
        $business = $vendor->businessInfo;
        if (! $business) {
            return [
                'total_reviews' => 0,
                'average_rating' => 0,
                'replied_reviews' => 0,
                'unreplied_reviews' => 0,
                'rating_distribution' => [
                    '5_star' => 0,
                    '4_star' => 0,
                    '3_star' => 0,
                    '2_star' => 0,
                    '1_star' => 0,
                ],
            ];
        }

        $stats = Review::where('business_id', $business->id)
            ->selectRaw('
                COUNT(*) as total_reviews,
                AVG(rating) as average_rating,
                COUNT(CASE WHEN EXISTS (
                    SELECT 1 FROM review_replies WHERE review_replies.review_id = reviews.id
                ) THEN 1 END) as replied_reviews,
                COUNT(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM review_replies WHERE review_replies.review_id = reviews.id
                ) THEN 1 END) as unreplied_reviews,
                COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star_reviews,
                COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star_reviews,
                COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star_reviews,
                COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star_reviews,
                COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star_reviews
            ')
            ->first();

        return [
            'total_reviews' => (int) $stats->total_reviews,
            'average_rating' => round((float) $stats->average_rating, 2),
            'replied_reviews' => (int) $stats->replied_reviews,
            'unreplied_reviews' => (int) $stats->unreplied_reviews,
            'rating_distribution' => [
                '5_star' => (int) $stats->five_star_reviews,
                '4_star' => (int) $stats->four_star_reviews,
                '3_star' => (int) $stats->three_star_reviews,
                '2_star' => (int) $stats->two_star_reviews,
                '1_star' => (int) $stats->one_star_reviews,
            ],
        ];
    }

    /**
     * Verify that the vendor owns the business being reviewed.
     */
    private function verifyVendorOwnership(Review $review, User $vendor): void
    {
        $business = $vendor->businessInfo;
        if (! $business || $business->id !== $review->business_id) {
            abort(403, 'Unauthorized to reply to this review');
        }
    }
}
