<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoostPurchaseRequestDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = (new BoostPurchaseRequestResource($this->resource))->toArray($request);
        $business = $this->businessInfo;
        $user = $business?->user;
        $category = $business?->category;
        $payment = $this->payment;
        $reviewer = $this->reviewer;

        return array_merge($base, [
            'is_flagged' => (bool) $this->is_flagged,
            'waiting_rank' => $this->when(isset($this->waiting_rank), $this->waiting_rank),
            'business_detail' => $business ? [
                'id' => $business->id,
                'business_name' => $business->business_name,
                'business_description' => $business->business_description,
                'services_offered' => $business->services_offered,
                'phone' => $business->phone,
                'whatsapp' => $business->whatsapp,
                'website' => $business->website,
                'verification_status' => $business->verification_status?->value ?? $business->verification_status,
                'business_status' => $business->business_status?->value ?? $business->business_status,
                'category' => $category ? [
                    'id' => $category->id,
                    'name' => $category->name,
                ] : null,
            ] : null,
            'vendor' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
            ] : null,
            'payment' => $payment ? [
                'id' => $payment->id,
                'tx_ref' => $payment->tx_ref,
                'amount' => (float) $payment->amount,
                'status' => $payment->status instanceof \BackedEnum ? $payment->status->value : $payment->status,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ] : null,
            'reviewer' => $reviewer ? [
                'id' => $reviewer->id,
                'name' => $reviewer->name,
            ] : null,
        ]);
    }
}
