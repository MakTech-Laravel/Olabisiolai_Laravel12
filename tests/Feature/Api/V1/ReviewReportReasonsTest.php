<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewReportReasonsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_fetch_review_report_reasons(): void
    {
        $response = $this->getJson('/api/v1/review-report-reasons');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.reasons.0.value', 'illegal_or_fraudulent');
        $response->assertJsonPath('data.reasons.0.label', 'This is illegal/fraudulent');
    }
}
