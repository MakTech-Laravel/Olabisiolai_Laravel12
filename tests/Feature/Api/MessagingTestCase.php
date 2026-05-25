<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class MessagingTestCase extends TestCase
{
    use RefreshDatabase;
}
