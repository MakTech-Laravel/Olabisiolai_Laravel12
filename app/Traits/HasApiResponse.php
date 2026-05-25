<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

trait HasApiResponse
{
    protected function successResponse(
        mixed $data,
        string $message = 'OK',
        int $status = Response::HTTP_OK,
        ?array $meta = null,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->normalizeData($data),
            'errors' => null,
            'meta' => $meta ?? $this->buildMeta($data),
        ], $status);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function successWithMeta(
        mixed $data,
        string $message,
        array $meta,
        int $status = Response::HTTP_OK,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->normalizeData($data),
            'errors' => null,
            'meta' => $meta,
        ], $status);
    }

    protected function errorResponse(
        string $message,
        int $status = Response::HTTP_BAD_REQUEST,
        ?array $errors = null,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
            'meta' => null,
        ], $status);
    }

    private function normalizeData(mixed $data): mixed
    {
        if ($data instanceof JsonResource) {
            return $data->resolve();
        }

        if ($data instanceof ResourceCollection) {
            return $data->resolve();
        }

        return $data;
    }

    private function buildMeta(mixed $data): ?array
    {
        if ($data instanceof ResourceCollection) {
            $underlying = $data->resource;

            if ($underlying instanceof LengthAwarePaginator) {
                return [
                    'pagination' => [
                        'current_page' => $underlying->currentPage(),
                        'last_page' => $underlying->lastPage(),
                        'per_page' => $underlying->perPage(),
                        'total' => $underlying->total(),
                    ],
                ];
            }

            if ($underlying instanceof CursorPaginator) {
                return [
                    'pagination' => [
                        'per_page' => $underlying->perPage(),
                        'next_cursor' => $underlying->nextCursor()?->encode(),
                        'prev_cursor' => $underlying->previousCursor()?->encode(),
                        'has_more' => $underlying->hasMorePages(),
                    ],
                ];
            }
        }

        if ($data instanceof LengthAwarePaginator) {
            return [
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                ],
            ];
        }

        if ($data instanceof CursorPaginator) {
            return [
                'pagination' => [
                    'per_page' => $data->perPage(),
                    'next_cursor' => $data->nextCursor()?->encode(),
                    'prev_cursor' => $data->previousCursor()?->encode(),
                    'has_more' => $data->hasMorePages(),
                ],
            ];
        }

        return null;
    }
}
