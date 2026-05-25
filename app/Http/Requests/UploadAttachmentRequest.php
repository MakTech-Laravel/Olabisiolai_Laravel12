<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesMessagingApiUser;
use Illuminate\Foundation\Http\FormRequest;

final class UploadAttachmentRequest extends FormRequest
{
    use AuthorizesMessagingApiUser;

    public function authorize(): bool
    {
        return $this->messagingApiUserAuthorized();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('messaging.max_attachment_size_mb', 50) * 1024;

        return [
            'file' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,mp4,mp3,wav',
            ],
        ];
    }
}
