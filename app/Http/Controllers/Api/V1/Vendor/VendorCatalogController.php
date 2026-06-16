<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BusinessCatalogItemResource;
use App\Models\BusinessCatalogItem;
use App\Services\BusinessCatalogService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VendorCatalogController extends Controller
{
    public function __construct(
        private readonly BusinessCatalogService $catalogService,
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function index(Request $request): Response
    {
        try {
            $business = $this->catalogService->resolveBusinessForUser(
                $request->user('api'),
                $request->integer('business_id') ?: null,
            );

            $items = $this->catalogService->listForBusiness($business);

            return sendResponse(true, 'Catalog retrieved successfully.', [
                'items' => BusinessCatalogItemResource::collection($items),
                'catalog_locked' => ! $this->subscriptionService->hasActivePremium($business),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                $exception->getMessage(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'business_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['required', 'in:product,service'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_kobo' => ['nullable', 'integer', 'min:0'],
            'price_label' => ['nullable', 'string', 'max:64'],
            'price_from' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        try {
            $business = $this->catalogService->resolveBusinessForUser(
                $request->user('api'),
                isset($validated['business_id']) ? (int) $validated['business_id'] : null,
            );

            $item = $this->catalogService->createItem(
                $business,
                $validated,
                $request->file('image'),
            );

            return sendResponse(true, 'Catalog item added successfully.', [
                'item' => new BusinessCatalogItemResource($item),
            ], Response::HTTP_CREATED);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                collect($exception->errors())->flatten()->first() ?: $exception->getMessage(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, BusinessCatalogItem $catalogItem): Response
    {
        $validated = $request->validate([
            'business_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['sometimes', 'in:product,service'],
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_kobo' => ['nullable', 'integer', 'min:0'],
            'price_label' => ['nullable', 'string', 'max:64'],
            'price_from' => ['sometimes', 'boolean'],
            'remove_image' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        try {
            $business = $this->catalogService->resolveBusinessForUser(
                $request->user('api'),
                isset($validated['business_id']) ? (int) $validated['business_id'] : null,
            );

            $item = $this->catalogService->updateItem(
                $business,
                $catalogItem,
                $validated,
                $request->file('image'),
                (bool) ($validated['remove_image'] ?? false),
            );

            return sendResponse(true, 'Catalog item updated successfully.', [
                'item' => new BusinessCatalogItemResource($item),
            ]);
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                collect($exception->errors())->flatten()->first() ?: $exception->getMessage(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Request $request, BusinessCatalogItem $catalogItem): Response
    {
        try {
            $business = $this->catalogService->resolveBusinessForUser(
                $request->user('api'),
                $request->integer('business_id') ?: null,
            );

            $this->catalogService->deleteItem($business, $catalogItem);

            return sendResponse(true, 'Catalog item deleted successfully.');
        } catch (ValidationException $exception) {
            return sendResponse(
                false,
                collect($exception->errors())->flatten()->first() ?: $exception->getMessage(),
                ['errors' => $exception->errors()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
