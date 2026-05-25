<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        // Admin panel uses auth:admin_api (Admin model); it has no `role` column like User.
        $isAdmin = $user instanceof Admin || $user?->role === 'admin';
        $isVendor = $user?->role === 'vendor';

        $flaggedAtForDisplay = ($isAdmin && ! $this->is_approved)
            ? ($this->flagged_at ?? $this->updated_at)
            : null;

        return [
            'id' => $this->id,
            'reviewer_name' => $this->is_anonymous ? 'Anonymous' : $this->full_name,
            'is_anonymous' => $this->is_anonymous,
            'rating' => $this->rating,
            'review_text' => $this->review_text,
            'is_approved' => $this->is_approved,
            'is_flagged' => $this->when(
                $isAdmin,
                fn() => ! $this->is_approved
            ),
            'flag_reason' => $this->when($isAdmin && ! $this->is_approved, $this->flag_reason),
            'flagged_at' => $this->when(
                $isAdmin && ! $this->is_approved,
                fn() => $flaggedAtForDisplay?->format('Y-m-d H:i:s')
            ),
            'flagged_at_human' => $this->when(
                $isAdmin && ! $this->is_approved && $flaggedAtForDisplay !== null,
                fn() => $flaggedAtForDisplay->diffForHumans()
            ),
            'business' => [
                'id' => $this->business_id,
                'business_name' => $this->when(
                    $this->relationLoaded('business') && $this->business !== null,
                    fn() => $this->business->business_name
                ),
            ],
            'user' => $this->when(
                $isAdmin && $this->relationLoaded('user') && $this->user !== null,
                fn() => [
                    'id' => $this->user->id,
                    'name' => trim($this->user->first_name . ' ' . $this->user->last_name),
                    'email' => $this->user->email,
                ]
            ),
            'images' => $this->when(
                $this->relationLoaded('images'),
                fn() => $this->images->map(fn($image) => [
                    'id' => $image->id,
                    'url' => public_media_url($image->image_path, null),
                    'original_filename' => $image->original_filename,
                    'mime_type' => $image->mime_type,
                    'file_size' => $image->file_size,
                    'size_formatted' => $this->formatFileSize($image->file_size),
                ])->values()->all()
            ),
            'replies' => $this->when(
                $this->relationLoaded('replies'),
                fn() => ReviewReplyResource::collection($this->replies)
            ),
            'replies_count' => $this->when(
                $this->relationLoaded('replies'),
                fn() => $this->replies->count()
            ),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at->diffForHumans(),
            'updated_at' => $this->when(
                $this->updated_at && $this->updated_at->ne($this->created_at),
                fn() => [
                    'formatted' => $this->updated_at->format('Y-m-d H:i:s'),
                    'human' => $this->updated_at->diffForHumans(),
                ]
            ),
        ];
    }

    /**
     * Format file size in human readable format
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
