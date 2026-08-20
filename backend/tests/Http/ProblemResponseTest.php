<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\ProblemResponse;
use PHPUnit\Framework\TestCase;

final class ProblemResponseTest extends TestCase
{
    public function testCarriesStatusDetailAndContentType(): void
    {
        $response = ProblemResponse::create(409, 'Nie można wznowić projektu w tym stanie.');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(409, $body['status']);
        self::assertSame('Nie można wznowić projektu w tym stanie.', $body['detail']);
        self::assertArrayHasKey('type', $body);
        self::assertArrayHasKey('title', $body);
    }
}
