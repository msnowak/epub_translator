<?php

declare(strict_types=1);

namespace App\Ollama;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OllamaClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return list<string>
     */
    public function listModels(): array
    {
        try {
            /** @var array{models?: list<array{name?: string}>} $data */
            $data = $this->httpClient->request('GET', 'api/tags')->toArray();
        } catch (ExceptionInterface $exception) {
            throw new OllamaUnavailableException(
                \sprintf('Could not reach the Ollama server: %s', $exception->getMessage()),
                previous: $exception,
            );
        }

        $names = [];
        foreach ($data['models'] ?? [] as $model) {
            if (isset($model['name'])) {
                $names[] = $model['name'];
            }
        }

        return $names;
    }
}
