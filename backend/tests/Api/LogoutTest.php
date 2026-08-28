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

    public function testLoggingOutEndsEverySessionOfThatUser(): void
    {
        $this->createUser('reader@example.com');

        // Dwa logowania to dwie sesje tego samego uzytkownika - druga
        // przegladarka, telefon, cokolwiek. Wylogowanie ma zamknac obie.
        $this->authenticateAs();
        $this->authenticateAs();

        $repository = self::getContainer()->get(RefreshTokenRepository::class);
        self::assertCount(2, $repository->findAll());

        $this->request('DELETE', '/api/token/refresh');

        self::assertResponseStatusCodeSame(204);
        self::assertCount(0, $repository->findAll());
    }

    public function testLoggingOutLeavesOtherUsersAlone(): void
    {
        $this->createUser('other@example.com');
        $this->authenticateAs('other@example.com');

        $this->createUser('reader@example.com');
        $this->authenticateAs();

        $this->request('DELETE', '/api/token/refresh');

        $repository = self::getContainer()->get(RefreshTokenRepository::class);

        self::assertCount(1, $repository->findAll());
    }

    private function login(): void
    {
        $this->createUser('reader@example.com');
        $this->authenticateAs();
    }

    // Nazwane authenticateAs(), nie authenticate(): ApiTestCase juz deklaruje
    // protected authenticate(User $user): string, ktore wystawia gole JWT bez
    // zadnego wywolania HTTP, i kazdy inny plik testowy polega wlasnie na tym.
    private function authenticateAs(string $email = 'reader@example.com'): void
    {
        $this->request('POST', '/api/login_check', [
            'email' => $email,
            'password' => UserFactory::DEFAULT_PASSWORD,
        ]);

        self::assertResponseIsSuccessful();
    }
}
