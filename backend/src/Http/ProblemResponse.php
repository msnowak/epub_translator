<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * API Platform renders errors as RFC 7807 problem documents. The three
 * controllers that predate the resources are plain Symfony controllers with no
 * "_api_operation" attribute, so its listener never sees them - they build the
 * envelope here instead, and the whole API speaks one shape.
 */
final readonly class ProblemResponse
{
    public static function create(int $status, string $detail): JsonResponse
    {
        return new JsonResponse(
            [
                'type' => 'https://tools.ietf.org/html/rfc2616#section-10',
                'title' => 'An error occurred',
                'status' => $status,
                'detail' => $detail,
            ],
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
