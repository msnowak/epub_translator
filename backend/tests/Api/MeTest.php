<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

final class MeTest extends ApiTestCase
{
    public function testReturnsCurrentUser(): void
    {
        $user = $this->createUser('reader@example.com');
        $token = $this->authenticate($user);

        $this->request('GET', '/api/me', token: $token);

        self::assertResponseIsSuccessful();

        $payload = $this->payload();
        self::assertSame('reader@example.com', $payload['email']);
        self::assertSame((string) $user->getId(), $payload['id']);
        self::assertArrayNotHasKey('password', $payload);
    }

    public function testRequiresAuthentication(): void
    {
        $this->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }
}
