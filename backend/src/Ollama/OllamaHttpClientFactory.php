<?php

declare(strict_types=1);

namespace App\Ollama;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OllamaHttpClientFactory
{
    /**
     * Naglowek Authorization dokladamy tylko wtedy, gdy klucz jest ustawiony -
     * goly serwer Ollama zadnego nie oczekuje.
     */
    public static function create(
        HttpClientInterface $client,
        string $baseUri,
        string $apiKey,
        float $timeout,
    ): HttpClientInterface {
        $options = [
            'base_uri' => rtrim($baseUri, '/').'/',
            'timeout' => $timeout,
        ];

        if ('' !== $apiKey) {
            $options['auth_bearer'] = $apiKey;
        }

        return $client->withOptions($options);
    }
}
