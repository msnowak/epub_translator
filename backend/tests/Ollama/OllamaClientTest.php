<?php

declare(strict_types=1);

namespace App\Tests\Ollama;

use App\Ollama\OllamaClient;
use App\Ollama\OllamaUnavailableException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OllamaClientTest extends TestCase
{
    public function testListsModelNames(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode([
            'models' => [
                ['name' => 'llama3.1:8b'],
                ['name' => 'qwen2.5:7b'],
            ],
        ], JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]));

        $client = new OllamaClient($http);

        self::assertSame(['llama3.1:8b', 'qwen2.5:7b'], $client->listModels());
    }

    public function testReturnsEmptyListWhenServerHasNoModels(): void
    {
        $http = new MockHttpClient(new MockResponse('{"models":[]}', ['response_headers' => ['content-type' => 'application/json']]));

        self::assertSame([], (new OllamaClient($http))->listModels());
    }

    public function testThrowsWhenServerIsUnreachable(): void
    {
        $http = new MockHttpClient(static function (): never {
            throw new \Symfony\Component\HttpClient\Exception\TransportException('Connection refused');
        });

        $this->expectException(OllamaUnavailableException::class);

        (new OllamaClient($http))->listModels();
    }

    public function testThrowsOnErrorStatusCode(): void
    {
        $http = new MockHttpClient(new MockResponse('unauthorized', ['http_code' => 401]));

        $this->expectException(OllamaUnavailableException::class);

        (new OllamaClient($http))->listModels();
    }
}
