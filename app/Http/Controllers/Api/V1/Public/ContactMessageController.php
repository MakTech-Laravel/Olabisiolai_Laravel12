<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ContactMessageController extends Controller
{
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
