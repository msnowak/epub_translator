<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\UserFactory;

final class LoginTest extends ApiTestCase
{
    public function testWrongPasswordAnswersAProblemDocumentInPolish(): void
    {
        $this->createUser('reader@example.com');

        $this->request('POST', '/api/login_check', [
            'email' => 'reader@example.com',
            'password' => 'wrong-password',
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('Nieprawidłowy e-mail lub hasło.', $this->payload()['detail']);
    }

    public function testUnknownEmailAnswersTheSameWay(): void
    {
        $this->request('POST', '/api/login_check', [
            'email' => 'nobody@example.com',
            'password' => UserFactory::DEFAULT_PASSWORD,
        ]);

        self::assertResponseStatusCodeSame(401);
        // Ten sam komunikat co wyzej, celowo: rozroznienie "nie ma takiego
        // konta" od "zle haslo" mowi obcemu, kto ma tu konto.
        self::assertSame('Nieprawidłowy e-mail lub hasło.', $this->payload()['detail']);
    }

    public function testCorrectCredentialsStillReturnAToken(): void
    {
        $this->createUser('reader@example.com');

        $this->request('POST', '/api/login_check', [
            'email' => 'reader@example.com',
            'password' => UserFactory::DEFAULT_PASSWORD,
        ]);

        self::assertResponseIsSuccessful();
        self::assertNotSame('', $this->payload()['token']);
    }
}
