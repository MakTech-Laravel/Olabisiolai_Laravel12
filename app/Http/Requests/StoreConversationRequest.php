<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ConversationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $authId = $this->user('api')?->id;

        return [
            'type' => ['required', Rule::enum(ConversationType::class)],
            'name' => ['nullable', 'string', 'max:255'],
            'participants' => ['required', 'array', 'min:1', 'max:'.(int) config('messaging.max_participants_per_conversation', 50)],
            'participants.*' => [
                'required',
                'string',
                'max:36',
                Rule::exists('users', 'uuid'),
            ],
        ];
    }
}
