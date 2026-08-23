<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Repository\RefreshTokenRepository;
use App\Security\RefreshTokenManager;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\UserFactory;

final class LogoutTest extends ApiTestCase
{
    public function testLoggingOutClearsTheCookieAndRevokesTheToken(): void
    {
        $this->login();

        $repository = self::getContainer()->get(RefreshTokenRepository::class);
        self::assertCount(1, $repository->findAll());

        $this->request('DELETE', '/api/token/refresh');

        self::assertResponseStatusCodeSame(204);
        self::assertCount(0, $repository->findAll());

        $cookie = $this->client->getResponse()->headers->getCookies()[0] ?? null;

        if (null === $cookie) {
            self::fail('The logout response carried no cookie.');
        }

        self::assertSame(RefreshTokenManager::COOKIE_NAME, $cookie->getName());
        self::assertTrue($cookie->isCleared());
    }

    public function testRefreshingAfterLogoutFails(): void
    {
        $this->login();

        $this->request('DELETE', '/api/token/refresh');
        $this->request('POST', '/api/token/refresh');

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoggingOutWithoutACookieIsStillFine(): void
    {
        // Wylogowanie ma byc idempotentne: przycisk wcisniety dwa razy nie moze
        // wystawic uzytkownikowi bledu.
        $this->request('DELETE', '/api/token/refresh');

        self::assertResponseStatusCodeSame(204);
    }

    private function login(): void
    {
        $this->createUser('reader@example.com');

        $this->request('POST', '/api/login_check', [
            'email' => 'reader@example.com',
            'password' => UserFactory::DEFAULT_PASSWORD,
        ]);

        self::assertResponseIsSuccessful();
    }
}
