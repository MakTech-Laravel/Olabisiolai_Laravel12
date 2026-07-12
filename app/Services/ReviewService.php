<?php

namespace App\Services;

use App\Models\Review;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReviewService
{
    /**
     * Store a new review with optional images.
     */
    public function storeReview(array $data, ?User $user = null, ?array $files = null): Review
    {
        return DB::transaction(function () use ($data, $user, $files): Review {
            $isAnonymous = (bool) ($data['is_anonymous'] ?? false);
            $fullName = $this->resolveReviewerFullName($data, $user, $isAnonymous);

            $review = Review::create([
                'user_id' => $isAnonymous ? null : $user?->id,
                'business_id' => (int) $data['business_id'],
                'full_name' => $fullName,
                'is_anonymous' => $isAnonymous,
                'rating' => (int) $data['rating'],
                'review_text' => trim((string) $data['review_text']),
                'is_approved' => true,
            ]);

            // Handle file uploads in service
            if (! empty($files)) {
                foreach ($files as $image) {
                    if (! $image instanceof UploadedFile || ! $image->isValid()) {
                        continue;
                    }

                    $path = $image->store('review-images', 'public');

                    if ($path === false || ! Storage::disk('public')->exists($path)) {
                        continue;
                    }

                    $review->images()->create([
                        'image_path' => $path,
                        'original_filename' => $image->getClientOriginalName(),
                        'mime_type' => $image->getMimeType() ?: $image->getClientMimeType(),
                        'file_size' => $image->getSize(),
                    ]);
                }
            }

            return $review->load([
                'images:id,review_id,image_path,original_filename,mime_type,file_size,created_at,updated_at',
                'user:id,first_name,last_name,name,email,phone',
                'business.user:id,first_name,last_name,name,email,phone',
                'business.category:id,name,subcategories,icon',
            ]);
        });
    }

    /**
     * Paginated reviews written by a specific authenticated user.
     */
    public function getUserReviews(int $userId, array $filters = []): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;

        $query = Review::query()
            ->where('user_id', $userId)
            ->with([
                'images',
                'business:id,business_name,category_id,location_id',
                'business.category:id,name',
                'business.location:id,lga_name,state_name,city_name',
                'replies' => fn ($replyQuery) => $replyQuery->latest(),
            ])
            ->latest('created_at');

        if (isset($filters['rating'])) {
            $query->byRating((int) $filters['rating']);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get paginated reviews with filtering.
     */
    public function getReviews(array $filters = []): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;

        $query = Review::with(['images', 'user:id,first_name,last_name,email', 'business:id,business_name']);

        if (($filters['sort'] ?? 'recent') === 'top') {
            $query->orderByDesc('rating')->orderByDesc('created_at');
        } else {
            $query->latest('created_at');
        }

        if (isset($filters['business_id'])) {
            $query->where('business_id', '=', $filters['business_id']);
        }

        if (isset($filters['rating'])) {
            $query->byRating((int) $filters['rating']);
        }

        if (isset($filters['is_approved'])) {
            $query->where('is_approved', '=', $filters['is_approved']);
        }

        if (array_key_exists('is_flagged', $filters) && $filters['is_flagged'] !== null) {
            if ($filters['is_flagged']) {
                $query->where('is_approved', false);
            } else {
                $query->where('is_approved', true);
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('review_text', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Update a review's moderation fields.
     */
    public function updateReview(Review $review, array $data): Review
    {
        // Only allow is_approved and flag_reason field updates
        $updateData = [];
        $wasApproved = (bool) $review->is_approved;

        if (isset($data['is_approved'])) {
            $updateData['is_approved'] = (bool) $data['is_approved'];

            if ($data['is_approved'] === true) {
                $updateData['flag_reason'] = null;
                $updateData['flagged_at'] = null;
            } elseif ($wasApproved || $review->flagged_at === null) {
                $updateData['flagged_at'] = now();
            }
        }

        if (isset($data['flag_reason']) && isset($data['is_approved']) && $data['is_approved'] === false) {
            $updateData['flag_reason'] = trim((string) $data['flag_reason']);
        }

        // forceFill avoids production issues if $fillable / cached config drifts; keys are still whitelisted above.
        if ($updateData !== []) {
            $review->forceFill($updateData)->save();
        }

        $review->refresh();
        $review->load(['images', 'user', 'business']);

        return $review;
    }

    /**
     * Delete a review and its associated images.
     */
    public function deleteReview(Review $review): void
    {
        DB::transaction(function () use ($review) {
            $review->load('images');

            foreach ($review->images as $image) {
                if (filled($image->image_path)) {
                    Storage::disk('public')->delete($image->image_path);
                }
            }

            $review->images()->delete();
            $review->delete();
        });
    }

    /**
     * Bulk approve reviews.
     */
    public function bulkApprove(array $reviewIds): int
    {
        return Review::whereIn('id', $reviewIds)
            ->update([
                'is_approved' => true,
                'flag_reason' => null,
                'flagged_at' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Bulk flag reviews (set is_approved to false) with reason.
     */
    public function bulkFlag(array $reviewIds, string $flagReason): int
    {
        return Review::whereIn('id', $reviewIds)
            ->update([
                'is_approved' => false,
                'flag_reason' => trim($flagReason),
                'flagged_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Approved review aggregates for a single business (public detail).
     *
     * @return array{total_reviews: int, average_rating: float, rating_distribution: list<array{stars: int, count: int}>}
     */
    public function getBusinessReviewsSummary(int $businessId): array
    {
        $table = (new Review)->getTable();

        $stats = DB::table($table)
            ->where('business_id', $businessId)
            ->where('is_approved', true)
            ->selectRaw('
                COUNT(*) as total_reviews,
                AVG(rating) as average_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star_reviews,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star_reviews,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star_reviews,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star_reviews,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star_reviews
            ')
            ->first();

        $total = (int) ($stats->total_reviews ?? 0);

        return [
            'total_reviews' => $total,
            'average_rating' => $total === 0 ? 0.0 : round((float) $stats->average_rating, 1),
            'rating_distribution' => [
                ['stars' => 5, 'count' => (int) ($stats->five_star_reviews ?? 0)],
                ['stars' => 4, 'count' => (int) ($stats->four_star_reviews ?? 0)],
                ['stars' => 3, 'count' => (int) ($stats->three_star_reviews ?? 0)],
                ['stars' => 2, 'count' => (int) ($stats->two_star_reviews ?? 0)],
                ['stars' => 1, 'count' => (int) ($stats->one_star_reviews ?? 0)],
            ],
        ];
    }

    /**
     * Get aggregate statistics for all reviews.
     */
    public function getStatistics(): array
    {
        $stats = Review::query()
            ->selectRaw('
                COUNT(*) as total_reviews,
                COUNT(CASE WHEN is_approved = 1 THEN 1 END) as approved_reviews,
                COUNT(CASE WHEN is_approved = 0 THEN 1 END) as flagged_reviews,
                AVG(rating) as average_rating,
                COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star_reviews,
                COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star_reviews,
                COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star_reviews,
                COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star_reviews,
                COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star_reviews
            ')
            ->first();

        return [
            'total_reviews' => (int) $stats->total_reviews,
            'approved_reviews' => (int) $stats->approved_reviews,
            'flagged_reviews' => (int) $stats->flagged_reviews,
            'average_rating' => round((float) $stats->average_rating, 2),
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
     * Get recent reviews for dashboard widgets.
     */
    public function getRecentReviews(int $limit = 5): Collection
    {
        return Review::with(['user:id,first_name,last_name'])
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'user_id', 'full_name', 'is_anonymous', 'rating', 'review_text', 'is_approved', 'flag_reason', 'created_at']);
    }

    /**
     * Get flagged reviews that need admin attention.
     */
    public function getFlaggedReviews(int $limit = 10): Collection
    {
        return Review::with(['user:id,first_name,last_name'])
            ->flagged()
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Resolve the stored reviewer name from payload and authenticated user.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveReviewerFullName(array $data, ?User $user, bool $isAnonymous): string
    {
        if ($isAnonymous) {
            $provided = trim((string) ($data['full_name'] ?? ''));

            return $provided !== '' ? $provided : 'Anonymous';
        }

        if ($user !== null) {
            $fromUser = trim((string) ($user->name ?? ''));
            if ($fromUser === '') {
                $fromUser = trim(trim((string) ($user->first_name ?? '')).' '.trim((string) ($user->last_name ?? '')));
            }
            if ($fromUser === '' && ! empty($user->email)) {
                $fromUser = (string) strstr((string) $user->email, '@', true);
            }

            return $fromUser !== '' ? $fromUser : 'User';
        }

        return trim((string) ($data['full_name'] ?? ''));
    }
}
