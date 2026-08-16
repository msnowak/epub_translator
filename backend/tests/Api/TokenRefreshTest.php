<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Security\RefreshTokenManager;
use App\Tests\Support\ApiTestCase;

final class TokenRefreshTest extends ApiTestCase
{
    public function testLoginSetsHttpOnlyRefreshCookie(): void
    {
        $this->createUser('reader@example.com', 'haslo12345');

        $this->request('POST', '/api/login_check', ['email' => 'reader@example.com', 'password' => 'haslo12345']);

        $cookie = $this->client->getResponse()->headers->getCookies()[0] ?? null;

        self::assertNotNull($cookie);
        self::assertSame(RefreshTokenManager::COOKIE_NAME, $cookie->getName());
        self::assertTrue($cookie->isHttpOnly());
        self::assertNotSame('', (string) $cookie->getValue());
    }

    public function testRefreshReturnsNewJwtAndRotatesCookie(): void
    {
        $this->createUser('reader@example.com', 'haslo12345');
        $this->request('POST', '/api/login_check', ['email' => 'reader@example.com', 'password' => 'haslo12345']);
        $firstCookie = (string) $this->client->getResponse()->headers->getCookies()[0]->getValue();

        $this->request('POST', '/api/token/refresh');

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', $this->payload());

        $secondCookie = (string) $this->client->getResponse()->headers->getCookies()[0]->getValue();
        self::assertNotSame($firstCookie, $secondCookie, 'The refresh token must be rotated.');
    }

    public function testRefreshWorksWhileStaleTokenIsStillAttached(): void
    {
        $this->createUser('reader@example.com', 'haslo12345');
        $this->request('POST', '/api/login_check', ['email' => 'reader@example.com', 'password' => 'haslo12345']);

        // A browser interceptor retries with the stale token still attached -
        // this is exactly the moment refreshing has to work.
        $this->request('POST', '/api/token/refresh', token: 'stale.jwt.value');

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', $this->payload());
    }

    public function testOldRefreshTokenStopsWorkingAfterRotation(): void
    {
        $this->createUser('reader@example.com', 'haslo12345');
        $this->request('POST', '/api/login_check', ['email' => 'reader@example.com', 'password' => 'haslo12345']);
        $firstCookie = (string) $this->client->getResponse()->headers->getCookies()[0]->getValue();

        $this->request('POST', '/api/token/refresh');

        $this->client->getCookieJar()->set(
            new \Symfony\Component\BrowserKit\Cookie(RefreshTokenManager::COOKIE_NAME, $firstCookie, null, '/api/token/refresh', 'localhost'),
        );
        $this->request('POST', '/api/token/refresh');

        self::assertResponseStatusCodeSame(401);
    }

    public function testRefreshWithoutCookieIsRejected(): void
    {
        $this->request('POST', '/api/token/refresh');

        self::assertResponseStatusCodeSame(401);
    }
}
