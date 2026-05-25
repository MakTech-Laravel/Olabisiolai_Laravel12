<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ContactMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'page' => ['nullable', 'integer', 'min:1'],
                'status' => ['nullable', 'string', Rule::in(ContactMessage::statusValues())],
                'search' => ['nullable', 'string', 'max:255'],
            ]);

            $perPage = (int) ($validated['per_page'] ?? 15);
            $query = ContactMessage::query()->latest('created_at');

            if (! empty($validated['status'])) {
                if ($validated['status'] === ContactMessage::STATUS_READ) {
                    $query->whereIn('status', [ContactMessage::STATUS_READ, 'replied']);
                } else {
                    $query->where('status', $validated['status']);
                }
            }

            if (! empty($validated['search'])) {
                $term = '%' . trim($validated['search']) . '%';
                $query->where(function ($builder) use ($term) {
                    $builder
                        ->where('full_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('subject', 'like', $term)
                        ->orWhere('message', 'like', $term);
                });
            }

            $messages = $query->paginate(
                $perPage,
                ['*'],
                'page',
                (int) ($validated['page'] ?? 1),
            );

            $statusCounts = ContactMessage::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            return response()->json([
                'success' => true,
                'data' => ContactMessageResource::collection($messages->getCollection())->resolve(),
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                ],
                'counts' => [
                    'all' => ContactMessage::query()->count(),
                    'new' => (int) ($statusCounts[ContactMessage::STATUS_NEW] ?? 0),
                    'read' => (int) ($statusCounts[ContactMessage::STATUS_READ] ?? 0)
                        + (int) ($statusCounts['replied'] ?? 0),
                    'archived' => (int) ($statusCounts[ContactMessage::STATUS_ARCHIVED] ?? 0),
                ],
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->validator->errors()->first(),
                'errors' => $exception->errors(),
            ], 422);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'success' => false,
                'message' => 'Could not load contact messages.',
            ], 500);
        }
    }

    public function show(ContactMessage $contactMessage): JsonResponse
    {
        if ($contactMessage->status === ContactMessage::STATUS_NEW) {
            $contactMessage->update([
                'status' => ContactMessage::STATUS_READ,
                'read_at' => now(),
            ]);
            $contactMessage->refresh();
        }

        return response()->json([
            'success' => true,
            'data' => new ContactMessageResource($contactMessage),
        ]);
    }

    public function update(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => ['nullable', 'string', Rule::in(ContactMessage::statusValues())],
                'admin_notes' => ['nullable', 'string', 'max:5000'],
            ]);

            $updates = [];

            if (array_key_exists('status', $validated) && $validated['status'] !== null) {
                $updates['status'] = $validated['status'];
                if ($validated['status'] === ContactMessage::STATUS_READ && $contactMessage->read_at === null) {
                    $updates['read_at'] = now();
                }
            }

            if (array_key_exists('admin_notes', $validated)) {
                $updates['admin_notes'] = $validated['admin_notes'];
            }

            if ($updates !== []) {
                $contactMessage->update($updates);
            }

            return response()->json([
                'success' => true,
                'message' => 'Contact message updated.',
                'data' => new ContactMessageResource($contactMessage->fresh()),
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->validator->errors()->first(),
                'errors' => $exception->errors(),
            ], 422);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'success' => false,
                'message' => 'Could not update contact message.',
            ], 500);
        }
    }

    public function destroy(ContactMessage $contactMessage): JsonResponse
    {
        if ($contactMessage->status !== ContactMessage::STATUS_ARCHIVED) {
            return response()->json([
                'success' => false,
                'message' => 'Move this message to Archived before permanent deletion.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $contactMessage->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact message permanently deleted.',
            'action' => 'deleted',
        ]);
    }
}
