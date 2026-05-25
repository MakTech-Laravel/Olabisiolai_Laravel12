<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'parent_id' => null,
            'body' => fake()->sentence(),
            'type' => MessageType::Text,
            'status' => MessageStatus::Sent,
        ];
    }
}
