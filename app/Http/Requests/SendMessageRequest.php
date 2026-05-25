<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesMessagingApiUser;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class SendMessageRequest extends FormRequest
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
            'body' => ['nullable', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => [
                'file',
                'max:'.$maxKb,
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,mp4,mp3,wav',
            ],
            'attachment_ids' => ['sometimes', 'array', 'max:50'],
            'attachment_ids.*' => ['integer', 'exists:attachments,id'],
            'parent_uuid' => ['nullable', 'uuid', 'exists:messages,uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('body') && trim((string) $this->input('body')) !== '') {
                return;
            }

            if ($this->hasFile('attachments')) {
                return;
            }

            if ($this->filled('attachment_ids') && $this->input('attachment_ids') !== []) {
                return;
            }

            $validator->errors()->add('body', 'Provide a message body, uploaded files, or attachment ids.');
        });
    }
}
