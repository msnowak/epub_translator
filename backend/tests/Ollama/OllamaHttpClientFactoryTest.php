<?php

declare(strict_types=1);

namespace App\Tests\Ollama;

use App\Ollama\OllamaHttpClientFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OllamaHttpClientFactoryTest extends TestCase
{
    public function testBaseUriWithoutTrailingSlashResolvesRelativePathAndSendsNoAuthHeaderWhenKeyIsEmpty(): void
    {
        $mockResponse = new MockResponse();
        $mock = new MockHttpClient($mockResponse);

        $client = OllamaHttpClientFactory::create($mock, 'http://ollama.example.com:11434', '', 5.0);
        $client->request('GET', 'api/tags')->getStatusCode();

        self::assertSame('http://ollama.example.com:11434/api/tags', $mockResponse->getRequestUrl());

        $headers = $mockResponse->getRequestOptions()['headers'] ?? [];
        self::assertIsArray($headers);
        foreach ($headers as $header) {
            self::assertStringStartsNotWith('Authorization:', $header);
        }
    }

    public function testAuthorizationHeaderIsPresentWhenApiKeyIsConfigured(): void
    {
        $mockResponse = new MockResponse();
        $mock = new MockHttpClient($mockResponse);

        $client = OllamaHttpClientFactory::create($mock, 'http://ollama.example.com:11434', 'secret-key', 5.0);
        $client->request('GET', 'api/tags')->getStatusCode();

        self::assertSame('http://ollama.example.com:11434/api/tags', $mockResponse->getRequestUrl());

        $headers = $mockResponse->getRequestOptions()['headers'] ?? [];
        self::assertContains('Authorization: Bearer secret-key', $headers);
    }
}
