<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MessageReportReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageReportRequest;
use App\Http\Resources\Api\V1\MessageReportResource;
use App\Models\Message;
use App\Services\MessageReportService;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MessageReportController extends Controller
{
    public function __construct(
        private readonly MessageReportService $messageReportService,
    ) {}

    public function reasons(): JsonResponse
    {
        $reasons = array_map(
            fn (MessageReportReason $reason) => [
                'value' => $reason->value,
                'label' => $reason->label(),
            ],
            MessageReportReason::cases(),
        );

        return sendResponse(true, 'Message report reasons retrieved successfully.', [
            'reasons' => array_values($reasons),
        ]);
    }

    public function store(StoreMessageReportRequest $request, Message $message): Response
    {
        try {
            $report = $this->messageReportService->storeReport(
                $message,
                $request->user('api'),
                $request->validated(),
            );

            return sendResponse(
                true,
                'Thank you for your report. An admin will review this chat message.',
                ['report' => new MessageReportResource($report)],
                Response::HTTP_CREATED,
            );
        } catch (RuntimeException $exception) {
            return sendResponse(false, $exception->getMessage(), null, Response::HTTP_CONFLICT);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
