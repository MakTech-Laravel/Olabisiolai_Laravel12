<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorPaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VendorPaymentMethodsController extends Controller
{
    public function index(Request $request)
    {
        try {
            $vendor = $request->user('api');

            $methods = VendorPaymentMethod::query()
                ->where('user_id', $vendor->id)
                ->orderByDesc('is_default')
                ->orderByDesc('id')
                ->get()
                ->map(fn(VendorPaymentMethod $m) => $this->toArray($m));

            return sendResponse(true, 'Payment methods retrieved successfully.', [
                'items' => $methods,
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        try {
            $vendor = $request->user('api');

            $validated = $request->validate([
                'label' => ['nullable', 'string', 'max:80'],
                'cardholder_name' => ['required', 'string', 'max:160'],
                'email' => ['required', 'email', 'max:255'],
                'phone' => ['required', 'string', 'max:32'],
                'last_four' => ['nullable', 'string', 'size:4', 'regex:/^[0-9]{4}$/'],
                'card_brand' => ['nullable', 'string', 'max:48'],
                'exp_month' => ['nullable', 'string', 'max:2'],
                'exp_year' => ['nullable', 'string', 'max:4'],
                'billing_line1' => ['nullable', 'string', 'max:255'],
                'billing_city' => ['nullable', 'string', 'max:120'],
                'billing_state' => ['nullable', 'string', 'max:120'],
                'billing_country' => ['nullable', 'string', 'max:120'],
                'is_default' => ['sometimes', 'boolean'],
            ]);

            $method = DB::transaction(function () use ($vendor, $validated): VendorPaymentMethod {
                $isDefault = (bool) ($validated['is_default'] ?? false);

                if ($isDefault) {
                    VendorPaymentMethod::query()
                        ->where('user_id', $vendor->id)
                        ->update(['is_default' => false]);
                }

                return VendorPaymentMethod::query()->create([
                    'user_id' => $vendor->id,
                    'label' => $validated['label'] ?? null,
                    'cardholder_name' => $validated['cardholder_name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'last_four' => $validated['last_four'] ?? null,
                    'card_brand' => $validated['card_brand'] ?? null,
                    'exp_month' => $validated['exp_month'] ?? null,
                    'exp_year' => $validated['exp_year'] ?? null,
                    'billing_line1' => $validated['billing_line1'] ?? null,
                    'billing_city' => $validated['billing_city'] ?? null,
                    'billing_state' => $validated['billing_state'] ?? null,
                    'billing_country' => $validated['billing_country'] ?? null,
                    'is_default' => $isDefault,
                ]);
            });

            return sendResponse(true, 'Payment method saved successfully.', [
                'payment_method' => $this->toArray($method),
            ], Response::HTTP_CREATED);
        } catch (ValidationException $exception) {
            return sendResponse(false, $exception->validator->errors()->first(), ['errors' => $exception->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function setDefault(Request $request, int $paymentMethod)
    {
        try {
            $vendor = $request->user('api');

            $method = VendorPaymentMethod::query()
                ->where('user_id', $vendor->id)
                ->whereKey($paymentMethod)
                ->first();

            if ($method === null) {
                return sendResponse(false, 'Payment method not found.', null, Response::HTTP_NOT_FOUND);
            }

            DB::transaction(function () use ($vendor, $method): void {
                VendorPaymentMethod::query()
                    ->where('user_id', $vendor->id)
                    ->update(['is_default' => false]);

                VendorPaymentMethod::query()
                    ->where('user_id', $vendor->id)
                    ->whereKey($method->id)
                    ->update(['is_default' => true]);
            });

            $fresh = $method->fresh();
            if ($fresh === null) {
                return sendResponse(false, 'Payment method not found.', null, Response::HTTP_NOT_FOUND);
            }

            return sendResponse(true, 'Default payment method updated.', [
                'payment_method' => $this->toArray($fresh),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Request $request, int $paymentMethod)
    {
        try {
            $vendor = $request->user('api');

            $method = VendorPaymentMethod::query()
                ->where('user_id', $vendor->id)
                ->whereKey($paymentMethod)
                ->first();

            if ($method === null) {
                return sendResponse(false, 'Payment method not found.', null, Response::HTTP_NOT_FOUND);
            }

            $wasDefault = $method->is_default;
            $method->delete();

            if ($wasDefault) {
                $next = VendorPaymentMethod::query()
                    ->where('user_id', $vendor->id)
                    ->orderByDesc('id')
                    ->first();

                if ($next !== null) {
                    $next->update(['is_default' => true]);
                }
            }

            return sendResponse(true, 'Payment method removed.', null);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(VendorPaymentMethod $m): array
    {
        return [
            'id' => $m->id,
            'label' => $m->label,
            'cardholder_name' => $m->cardholder_name,
            'email' => $m->email,
            'phone' => $m->phone,
            'last_four' => $m->last_four,
            'card_brand' => $m->card_brand,
            'exp_month' => $m->exp_month,
            'exp_year' => $m->exp_year,
            'billing_line1' => $m->billing_line1,
            'billing_city' => $m->billing_city,
            'billing_state' => $m->billing_state,
            'billing_country' => $m->billing_country,
            'is_default' => $m->is_default,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
