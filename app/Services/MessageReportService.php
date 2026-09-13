<?php

namespace App\Services;

use App\Enums\MessageReportReason;
use App\Enums\ReviewReportStatus;
use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\Message;
use App\Models\MessageReport;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class MessageReportService
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function storeReport(Message $message, User $reporter, array $data): MessageReport
    {
        $message->loadMissing(['conversation.participantRows', 'sender']);

        $isParticipant = $message->conversation->participantRows
            ->contains('user_id', $reporter->id);

        if (! $isParticipant) {
            throw new RuntimeException('You can only report messages from your own conversations.');
        }

        if ((int) $message->sender_id === (int) $reporter->id) {
            throw new RuntimeException('You cannot report your own message.');
        }

        $reason = MessageReportReason::from($data['reason']);

        try {
            $report = MessageReport::query()->create([
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'reporter_user_id' => $reporter->id,
                'reported_user_id' => $message->sender_id,
                'reason' => $reason,
                'description' => isset($data['description']) ? trim((string) $data['description']) : null,
                'status' => ReviewReportStatus::Pending,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new RuntimeException('You have already reported this message.');
        }

        return $report->load($this->relations());
    }

    public function getReports(array $filters = []): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 15;

        $query = MessageReport::query()
            ->with($this->relations())
            ->latest('created_at');

        if (isset($filters['status'])) {
            $query->where('status', ReviewReportStatus::from($filters['status']));
        }

        if (isset($filters['reason'])) {
            $query->where('reason', MessageReportReason::from($filters['reason']));
        }

        if (isset($filters['reported_user_id'])) {
            $query->where('reported_user_id', $filters['reported_user_id']);
        }

        if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
            $search = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($q) use ($search): void {
                $q->whereHas('message', fn ($m) => $m->where('body', 'like', $search))
                    ->orWhereHas('reporter', fn ($u) => $u
                        ->where('email', 'like', $search)
                        ->orWhere('first_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search))
                    ->orWhereHas('reportedUser', fn ($u) => $u
                        ->where('email', 'like', $search)
                        ->orWhere('first_name', 'like', $search)
                        ->orWhere('last_name', 'like', $search));
            });
        }

        return $query->paginate($perPage);
    }

    public function dismissReport(MessageReport $report, ?Admin $admin = null, ?string $note = null): MessageReport
    {
        $report->update([
            'status' => ReviewReportStatus::Dismissed,
            'reviewed_at' => now(),
            'reviewed_by_admin_id' => $admin?->id,
            'admin_note' => $note,
        ]);

        return $report->fresh($this->relations());
    }

    public function resolveReport(MessageReport $report, ?Admin $admin = null, ?string $note = null): MessageReport
    {
        $report->update([
            'status' => ReviewReportStatus::Reviewed,
            'reviewed_at' => now(),
            'reviewed_by_admin_id' => $admin?->id,
            'admin_note' => $note,
        ]);

        return $report->fresh($this->relations());
    }

    public function emailReportedUser(
        MessageReport $report,
        Admin $admin,
        string $subject,
        string $body,
    ): MessageReport {
        $report->loadMissing('reportedUser');
        $user = $report->reportedUser;

        if (! $user instanceof User || ! filled($user->email)) {
            throw new RuntimeException('The reported user does not have an email address.');
        }

        Mail::raw($body, function ($message) use ($user, $subject): void {
            $message->to($user->email)->subject($subject);
        });

        $report->update([
            'status' => ReviewReportStatus::Reviewed,
            'reviewed_at' => now(),
            'reviewed_by_admin_id' => $admin->id,
            'last_admin_email_subject' => $subject,
            'last_admin_email_body' => $body,
            'last_admin_email_sent_at' => now(),
        ]);

        return $report->fresh($this->relations());
    }

    public function suspendReportedUser(MessageReport $report, Admin $admin, ?string $note = null): MessageReport
    {
        $report->loadMissing('reportedUser');
        $user = $report->reportedUser;

        if (! $user instanceof User) {
            throw new RuntimeException('Reported user not found.');
        }

        $this->users->changeStatus($user, UserStatus::Suspended->value);
        $this->users->revokeAllTokens($user);

        $report->update([
            'status' => ReviewReportStatus::Reviewed,
            'reviewed_at' => now(),
            'reviewed_by_admin_id' => $admin->id,
            'admin_note' => $note ?: 'Reported user suspended.',
        ]);

        return $report->fresh($this->relations());
    }

    public function pendingCount(): int
    {
        return MessageReport::pending()->count();
    }

    private function relations(): array
    {
        return [
            'message:id,uuid,conversation_id,sender_id,body,type,created_at',
            'conversation:id,uuid',
            'reporter:id,first_name,last_name,email,role,status',
            'reportedUser:id,first_name,last_name,email,role,status',
        ];
    }
}
