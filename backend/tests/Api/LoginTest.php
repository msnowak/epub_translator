<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;

final class LoginTest extends ApiTestCase
{
    public function testValidCredentialsReturnToken(): void
    {
        $this->createUser('reader@example.com', 'haslo12345');

        $this->request('POST', '/api/login_check', ['email' => 'reader@example.com', 'password' => 'haslo12345']);

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', $this->payload());
    }

    public function testWrongPasswordIsRejected(): void
    {
        $this->createUser('reader@example.com', 'haslo12345');

        $this->request('POST', '/api/login_check', ['email' => 'reader@example.com', 'password' => 'zle-haslo']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testUnknownEmailIsRejected(): void
    {
        $this->request('POST', '/api/login_check', ['email' => 'nikt@example.com', 'password' => 'haslo12345']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testProtectedEndpointRequiresToken(): void
    {
        $this->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }
}
