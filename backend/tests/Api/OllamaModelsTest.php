<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OllamaModelsTest extends ApiTestCase
{
    public function testReturnsModelsForAuthenticatedUser(): void
    {
        self::getContainer()->set('ollama.http_client', new MockHttpClient(
            new MockResponse('{"models":[{"name":"llama3.1:8b"}]}', [
                'response_headers' => ['content-type' => 'application/json'],
            ]),
        ));

        $token = $this->authenticate($this->createUser());

        $this->request('GET', '/api/ollama/models', token: $token);

        self::assertResponseIsSuccessful();
        self::assertSame(['llama3.1:8b'], $this->payload()['models']);
    }

    public function testRequiresAuthentication(): void
    {
        $this->request('GET', '/api/ollama/models');

        self::assertResponseStatusCodeSame(401);
    }

    public function testReturnsServiceUnavailableWhenOllamaIsDown(): void
    {
        self::getContainer()->set('ollama.http_client', new MockHttpClient(static function (): never {
            throw new TransportException('Connection refused');
        }));

        $token = $this->authenticate($this->createUser());

        $this->request('GET', '/api/ollama/models', token: $token);

        self::assertResponseStatusCodeSame(503);
        self::assertArrayHasKey('message', $this->payload());
    }
}
