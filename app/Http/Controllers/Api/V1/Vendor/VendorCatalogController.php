<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BusinessCatalogItemResource;
use App\Models\BusinessCatalogItem;
use App\Services\BusinessCatalogService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
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
            $validated = $request->validate([
                'business_id' => ['nullable', 'integer', 'min:1'],
            ]);

            $business = $this->catalogService->resolveBusinessForUser(
                $request->user('api'),
                isset($validated['business_id']) ? (int) $validated['business_id'] : null,
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
        $this->normalizeCatalogPriceInput($request);

        $validated = $request->validate([
            'business_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['required', 'in:product,service'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_kobo' => ['nullable', 'integer', 'min:0'],
            'price_label' => ['nullable', 'string', 'max:64'],
            'price_from' => ['sometimes', 'boolean'],
            'discount_type' => ['sometimes', 'nullable', Rule::in(['percent', 'flat'])],
            'discount_value' => ['nullable', 'integer', 'min:1'],
            'images' => ['nullable', 'array'],
            'images.*' => ['required', 'image', 'max:10240'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $this->assertCatalogDiscountRules($validated);
        try {
            $business = $this->catalogService->resolveBusinessForUser(
                $request->user('api'),
                isset($validated['business_id']) ? (int) $validated['business_id'] : null,
            );

            $item = $this->catalogService->createItem(
                $business,
                $validated,
                $this->normalizeUploadedImages($request),
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
        $this->normalizeCatalogPriceInput($request);

        $validated = $request->validate([
            'business_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['sometimes', 'in:product,service'],
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_kobo' => ['nullable', 'integer', 'min:0'],
            'price_label' => ['nullable', 'string', 'max:64'],
            'price_from' => ['sometimes', 'boolean'],
            'discount_type' => ['sometimes', 'nullable', Rule::in(['percent', 'flat'])],
            'discount_value' => ['nullable', 'integer', 'min:1'],
            'remove_image' => ['sometimes', 'boolean'],
            'remove_images' => ['sometimes', 'boolean'],
            'keep_image_paths' => ['nullable', 'array'],
            'keep_image_paths.*' => ['required', 'string', 'max:500'],
            'images' => ['nullable', 'array'],
            'images.*' => ['required', 'image', 'max:10240'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $this->assertCatalogDiscountRules($validated);
        try {
            $business = $this->catalogService->resolveBusinessForUser(
                $request->user('api'),
                isset($validated['business_id']) ? (int) $validated['business_id'] : null,
            );

            $keepImagePaths = array_key_exists('keep_image_paths', $validated)
                ? array_values($validated['keep_image_paths'] ?? [])
                : null;

            $item = $this->catalogService->updateItem(
                $business,
                $catalogItem,
                $validated,
                $this->normalizeUploadedImages($request),
                (bool) ($validated['remove_images'] ?? $validated['remove_image'] ?? false),
                $keepImagePaths,
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
            $validated = $request->validate([
                'business_id' => ['nullable', 'integer', 'min:1'],
            ]);

            $business = $this->catalogService->resolveBusinessForUser(
                $request->user('api'),
                isset($validated['business_id']) ? (int) $validated['business_id'] : null,
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

    /**
     * @return list<UploadedFile>
     */
    private function normalizeUploadedImages(Request $request): array
    {
        $images = $request->file('images', []);
        if (! is_array($images)) {
            $images = $images instanceof UploadedFile ? [$images] : [];
        }

        $images = array_values(array_filter(
            $images,
            static fn ($file) => $file instanceof UploadedFile,
        ));

        $single = $request->file('image');
        if ($single instanceof UploadedFile) {
            $images[] = $single;
        }

        return $images;
    }

    /**
     * FormData sends empty strings; coerce blank price fields to null before validation.
     * Numeric amounts use price_kobo; free-text / ranges like "from 1500 - 2000" use price_label.
     * Vendor `price_kobo` is the list/base amount; discount fields compute the stored sale price.
     */
    private function normalizeCatalogPriceInput(Request $request): void
    {
        $merge = [];

        if ($request->exists('price_kobo')) {
            $priceKobo = $request->input('price_kobo');
            $merge['price_kobo'] = ($priceKobo === '' || $priceKobo === null) ? null : $priceKobo;
        }

        if ($request->exists('price_label')) {
            $priceLabel = $request->input('price_label');
            $merge['price_label'] = is_string($priceLabel) && trim($priceLabel) === ''
                ? null
                : $priceLabel;
        }

        if ($request->exists('discount_type')) {
            $type = $request->input('discount_type');
            $merge['discount_type'] = ($type === '' || $type === null) ? null : $type;
        }

        if ($request->exists('discount_value')) {
            $value = $request->input('discount_value');
            $merge['discount_value'] = ($value === '' || $value === null) ? null : $value;
        }

        if ($merge !== []) {
            $request->merge($merge);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertCatalogDiscountRules(array $validated): void
    {
        if (! array_key_exists('discount_type', $validated) && ! array_key_exists('discount_value', $validated)) {
            return;
        }

        $type = $validated['discount_type'] ?? null;
        $value = $validated['discount_value'] ?? null;
        $priceFrom = (bool) ($validated['price_from'] ?? false);
        $listKobo = array_key_exists('price_kobo', $validated) && $validated['price_kobo'] !== null
            ? (int) $validated['price_kobo']
            : null;

        if ($type === null && ($value === null || $value === '')) {
            return;
        }

        if ($type === null || $value === null) {
            throw ValidationException::withMessages([
                'discount_type' => ['Provide both discount_type and discount_value, or clear both.'],
            ]);
        }

        if ($listKobo === null) {
            throw ValidationException::withMessages([
                'discount_type' => ['Discounts require an exact price_kobo.'],
            ]);
        }

        if ($priceFrom) {
            throw ValidationException::withMessages([
                'discount_type' => ['Discounts cannot be used with “from” pricing.'],
            ]);
        }

        if ($type === 'percent' && (int) $value > 100) {
            throw ValidationException::withMessages([
                'discount_value' => ['Percentage discount must be between 1 and 100.'],
            ]);
        }

        if ($type === 'flat' && (int) $value > $listKobo) {
            throw ValidationException::withMessages([
                'discount_value' => ['Flat discount cannot exceed the list price.'],
            ]);
        }
    }
}
