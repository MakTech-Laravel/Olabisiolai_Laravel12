<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\MessageType;
use App\Enums\UserStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message_id' => $this->message_id,
            'message_uuid' => $this->message?->uuid,
            'conversation_id' => $this->conversation_id,
            'conversation_uuid' => $this->conversation?->uuid,
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->label(),
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i:s'),
            'admin_note' => $this->admin_note,
            'last_admin_email_subject' => $this->last_admin_email_subject,
            'last_admin_email_sent_at' => $this->last_admin_email_sent_at?->format('Y-m-d H:i:s'),
            'message' => $this->when(
                $this->relationLoaded('message') && $this->message !== null,
                fn () => [
                    'uuid' => $this->message->uuid,
                    'body' => $this->message->body,
                    'type' => $this->message->type instanceof MessageType ? $this->message->type->value : $this->message->type,
                    'created_at' => $this->message->created_at?->format('Y-m-d H:i:s'),
                ],
            ),
            'reporter' => $this->when(
                $this->relationLoaded('reporter') && $this->reporter !== null,
                fn () => [
                    'id' => $this->reporter->id,
                    'name' => trim($this->reporter->first_name.' '.$this->reporter->last_name),
                    'email' => $this->reporter->email,
                    'role' => $this->reporter->role,
                    'status' => $this->reporter->status instanceof UserStatus ? $this->reporter->status->value : $this->reporter->status,
                ],
            ),
            'reported_user' => $this->when(
                $this->relationLoaded('reportedUser') && $this->reportedUser !== null,
                fn () => [
                    'id' => $this->reportedUser->id,
                    'name' => trim($this->reportedUser->first_name.' '.$this->reportedUser->last_name),
                    'email' => $this->reportedUser->email,
                    'role' => $this->reportedUser->role,
                    'status' => $this->reportedUser->status instanceof UserStatus ? $this->reportedUser->status->value : $this->reportedUser->status,
                ],
            ),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }
}
