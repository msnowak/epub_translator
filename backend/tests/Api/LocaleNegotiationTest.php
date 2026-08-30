<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LocaleNegotiationTest extends WebTestCase
{
    public function testAcceptLanguageSelectsEnglish(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login_check', server: [
            'HTTP_ACCEPT_LANGUAGE' => 'en',
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'nobody@example.com', 'password' => 'wrong'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(401);
        self::assertSame(
            'Invalid email or password.',
            json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['detail'],
        );
    }

    public function testNoHeaderFallsBackToPolish(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login_check', server: [
            'CONTENT_TYPE' => 'application/json',
            // Symfony\Component\HttpFoundation\Request::create() bakes in
            // 'en-us,en;q=0.5' as a default Accept-Language whenever the
            // caller does not set one - a fixture for tests, not a real
            // client's behaviour. Overriding it back to null is the only way
            // to actually exercise "no header was sent" here.
            'HTTP_ACCEPT_LANGUAGE' => null,
        ], content: json_encode(['email' => 'nobody@example.com', 'password' => 'wrong'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(401);
        self::assertSame(
            'Nieprawidłowy e-mail lub hasło.',
            json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['detail'],
        );
    }

    public function testUnsupportedLanguageFallsBackToPolish(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login_check', server: [
            'HTTP_ACCEPT_LANGUAGE' => 'de',
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['email' => 'nobody@example.com', 'password' => 'wrong'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(401);
        self::assertSame(
            'Nieprawidłowy e-mail lub hasło.',
            json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['detail'],
        );
    }
}
