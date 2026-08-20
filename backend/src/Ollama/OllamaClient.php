<?php

declare(strict_types=1);

namespace App\Ollama;

use App\Translation\TranslationEngineInterface;
use App\Translation\TranslationRequest;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OllamaClient implements TranslationEngineInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(float:OLLAMA_TEMPERATURE)%')]
        private float $temperature,
    ) {
    }

    public function translate(TranslationRequest $request): string
    {
        try {
            /** @var array{message?: mixed} $data */
            $data = $this->httpClient->request('POST', 'api/chat', [
                'json' => [
                    'model' => $request->model,
                    'stream' => false,
                    'options' => ['temperature' => $this->temperature],
                    'messages' => [
                        ['role' => 'system', 'content' => $request->systemPrompt],
                        ['role' => 'user', 'content' => $request->userPrompt],
                    ],
                ],
            ])->toArray();
        } catch (ExceptionInterface $exception) {
            throw new OllamaUnavailableException(
                \sprintf('Could not reach the Ollama server: %s', $exception->getMessage()),
                previous: $exception,
            );
        }

        $message = $data['message'] ?? null;

        if (!\is_array($message) || !isset($message['content']) || !\is_string($message['content'])) {
            throw new OllamaUnavailableException('Ollama server returned no message content.');
        }

        return $message['content'];
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
