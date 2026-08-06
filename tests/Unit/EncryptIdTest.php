<?php

namespace Tests\Unit;

use App\Support\EncryptId;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EncryptIdTest extends TestCase
{
    /**
     * Parity with olabisiolai_frontend_react/src/lib/encryptId.ts (Node Buffer / btoa).
     *
     * @return array<string, array{0: int, 1: string}>
     */
    public static function knownFrontendExamples(): array
    {
        return [
            'id 1' => [1, 'Z18x'],
            'id 20' => [20, 'Z18yMA'],
            'id 100' => [100, 'Z18xMDA'],
            'id 12345' => [12345, 'Z18xMjM0NQ'],
        ];
    }

    #[DataProvider('knownFrontendExamples')]
    public function test_encrypt_matches_frontend_algorithm(int $id, string $expected): void
    {
        $this->assertSame($expected, EncryptId::encrypt($id));
        $this->assertSame($id, EncryptId::decrypt($expected));
    }
}
