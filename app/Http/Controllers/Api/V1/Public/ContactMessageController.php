<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ContactMessageController extends Controller
{
    #[OA\Post(
        path: '/v1/contact-messages',
        summary: 'Submit a contact form message',
        description: 'Public, unauthenticated endpoint. Rate-limited to 10 requests/minute.',
        tags: ['Public'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['full_name', 'email', 'subject', 'message'],
                properties: [
                    new OA\Property(property: 'full_name', type: 'string', maxLength: 255, example: 'Ada Obi'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255),
                    new OA\Property(property: 'subject', type: 'string', maxLength: 500),
                    new OA\Property(property: 'message', type: 'string', maxLength: 5000),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Message sent successfully', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function store(Request $request): Response
    {
        try {
            $validated = $request->validate([
                'full_name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'subject' => ['required', 'string', 'max:500'],
                'message' => ['required', 'string', 'max:5000'],
            ]);

            $contactMessage = ContactMessage::query()->create([
                'full_name' => trim($validated['full_name']),
                'email' => strtolower(trim($validated['email'])),
                'subject' => trim($validated['subject']),
                'message' => trim($validated['message']),
                'status' => ContactMessage::STATUS_NEW,
            ]);

            return sendResponse(
                true,
                'Thank you — your message has been sent. We will respond soon.',
                new ContactMessageResource($contactMessage),
                Response::HTTP_CREATED,
            );
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(
                false,
                'Could not send your message. Please try again.',
                null,
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }
}
