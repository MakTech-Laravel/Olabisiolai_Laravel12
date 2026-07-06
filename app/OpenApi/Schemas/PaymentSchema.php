<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Payment',
    title: 'Payment',
    description: 'A gateway payment/invoice record — covers subscription, boost, verification, and wallet '
        .'top-up purposes (see PaymentService::toArray). There is no separate Invoice model; this is the '
        .'canonical billing record for both charges and their receipts.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 4821),
        new OA\Property(property: 'purpose', type: 'string', enum: ['verification', 'boosting', 'subscription', 'wallet_topup']),
        new OA\Property(property: 'reference_type', type: 'string', example: 'subscription'),
        new OA\Property(property: 'purpose_label', type: 'string', example: 'Subscription'),
        new OA\Property(property: 'package_id', type: 'integer', nullable: true),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 15000),
        new OA\Property(property: 'currency', type: 'string', example: 'NGN'),
        new OA\Property(property: 'tx_ref', type: 'string', example: 'GID-SUB-4821'),
        new OA\Property(property: 'gateway', type: 'string', enum: ['flutterwave', 'paystack'], nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'completed', 'failed']),
        new OA\Property(property: 'is_consumed', type: 'boolean'),
        new OA\Property(property: 'paid_at', type: 'string', nullable: true),
        new OA\Property(property: 'metadata', type: 'object', nullable: true),
    ],
    type: 'object',
)]
class PaymentSchema {}
