<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SearchSynonymResource;
use App\Services\SearchSynonymService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SearchSynonymController extends Controller
{
    public function __construct(private SearchSynonymService $searchSynonymService) {}

    public function allSynonyms(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'search' => ['nullable', 'string', 'max:255'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'page' => ['nullable', 'integer', 'min:1'],
            ]);

            $synonyms = $this->searchSynonymService->paginateSynonyms(
                isset($validated['search']) ? (string) $validated['search'] : null,
                $validated['per_page'] ?? 10
            );

            return sendResponse(true, 'Search synonyms retrieved successfully.', [
                'filter' => [
                    'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
                ],
                'count' => $synonyms->total(),
                'pagination' => [
                    'current_page' => $synonyms->currentPage(),
                    'per_page' => $synonyms->perPage(),
                    'last_page' => $synonyms->lastPage(),
                    'total' => $synonyms->total(),
                ],
                'synonyms' => SearchSynonymResource::collection($synonyms),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function createSynonym(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'term' => ['required', 'string', 'max:255', 'unique:search_synonyms,term'],
                'synonyms' => ['required'],
            ]);

            $synonym = $this->searchSynonymService->createSynonym($validated);

            return sendResponse(true, 'Search synonym created successfully.', [
                'synonym' => new SearchSynonymResource($synonym),
            ], Response::HTTP_CREATED);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function viewSynonym(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'id' => ['required', 'integer', 'exists:search_synonyms,id'],
            ]);

            $synonym = $this->searchSynonymService->getSynonymById((int) $validated['id']);

            return sendResponse(true, 'Search synonym retrieved successfully.', [
                'synonym' => new SearchSynonymResource($synonym),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateSynonym(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'id' => ['required', 'integer', 'exists:search_synonyms,id'],
                'term' => ['required', 'string', 'max:255', Rule::unique('search_synonyms', 'term')->ignore($request->input('id'))],
                'synonyms' => ['required'],
            ]);

            $synonym = $this->searchSynonymService->getSynonymById((int) $validated['id']);
            $synonym = $this->searchSynonymService->updateSynonym($synonym, $validated);

            return sendResponse(true, 'Search synonym updated successfully.', [
                'synonym' => new SearchSynonymResource($synonym),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteSynonym(Request $request)
    {
        try {
            if (! adminAuthCheck($request)) {
                return sendResponse(false, 'Admin access required.', null, Response::HTTP_UNAUTHORIZED);
            }

            $validated = $request->validate([
                'id' => ['required', 'integer', 'exists:search_synonyms,id'],
            ]);

            $synonym = $this->searchSynonymService->getSynonymById((int) $validated['id']);
            $this->searchSynonymService->deleteSynonym($synonym);

            return sendResponse(true, 'Search synonym deleted successfully.');
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->validator->errors()->first(),
                [
                    'errors' => $exception->errors(),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
