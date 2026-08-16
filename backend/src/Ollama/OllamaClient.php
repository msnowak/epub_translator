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
            // This is a metadata probe, not a generation request - it must not
            // be able to hold a worker for the (much longer) generation timeout
            // if the server is hung rather than simply unreachable.
            /** @var array{models?: mixed} $data */
            $data = $this->httpClient->request('GET', 'api/tags', ['timeout' => 10])->toArray();
        } catch (ExceptionInterface $exception) {
            throw new OllamaUnavailableException(
                \sprintf('Could not reach the Ollama server: %s', $exception->getMessage()),
                previous: $exception,
            );
        }

        $models = $data['models'] ?? [];
        if (!\is_array($models)) {
            throw new OllamaUnavailableException('Ollama server returned an unexpected response shape for "models".');
        }

        $names = [];
        foreach ($models as $model) {
            if (\is_array($model) && isset($model['name']) && \is_string($model['name'])) {
                $names[] = $model['name'];
            }
        }

        return $names;
    }
}
