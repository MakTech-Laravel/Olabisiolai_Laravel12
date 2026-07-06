<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * Unauthenticated location list for storefront filters (stable options while location_id is active).
 */
class PublicLocationCatalogController extends Controller
{
    #[OA\Get(
        path: '/v1/locations',
        summary: 'List all locations',
        tags: ['Public'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Locations retrieved successfully',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'locations', type: 'array', items: new OA\Items(properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'label', type: 'string', example: 'Ikeja, Lagos'),
                            new OA\Property(property: 'state_name', type: 'string'),
                            new OA\Property(property: 'city_name', type: 'string'),
                            new OA\Property(property: 'lga_name', type: 'string'),
                        ], type: 'object')),
                        new OA\Property(property: 'count', type: 'integer'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 500, description: 'Unexpected server error', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function index()
    {
        try {
            $rows = Location::query()
                ->orderBy('state_name')
                ->orderBy('lga_name')
                ->orderBy('city_name')
                ->get(['id', 'lga_name', 'city_name', 'state_name', 'formatted_address']);

            $locations = $rows->map(function (Location $loc) {
                return [
                    'id' => $loc->id,
                    'label' => self::filterLabel($loc),
                    'state_name' => trim((string) ($loc->state_name ?? '')),
                    'city_name' => trim((string) ($loc->city_name ?? '')),
                    'lga_name' => trim((string) ($loc->lga_name ?? '')),
                ];
            })->values()->all();

            return sendResponse(true, 'Locations retrieved successfully.', [
                'locations' => $locations,
                'count' => count($locations),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return sendResponse(false, 'Something went wrong. Please try again.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private static function filterLabel(Location $loc): string
    {
        $lga = trim((string) ($loc->lga_name ?? ''));
        $city = trim((string) ($loc->city_name ?? ''));
        $state = trim((string) ($loc->state_name ?? ''));
        if ($lga !== '') {
            return $state !== '' ? "{$lga}, {$state}" : $lga;
        }
        if ($city !== '') {
            return $state !== '' ? "{$city}, {$state}" : $city;
        }
        if ($state !== '') {
            return $state;
        }

        $formatted = trim((string) ($loc->formatted_address ?? ''));

        return $formatted !== '' ? $formatted : 'Location '.$loc->id;
    }
}
