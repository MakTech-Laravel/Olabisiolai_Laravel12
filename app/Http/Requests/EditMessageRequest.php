<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesMessagingApiUser;
use Illuminate\Foundation\Http\FormRequest;

final class EditMessageRequest extends FormRequest
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
        return [
            'body' => ['required', 'string', 'min:1', 'max:10000'],
        ];
    }
}
