<?php

declare(strict_types=1);

namespace App\Ollama;

use App\Translation\TranslationEngineInterface;
use App\Translation\TranslationRequest;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
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
        } catch (HttpExceptionInterface $exception) {
            throw new OllamaUnavailableException(
                \sprintf('The Ollama server rejected the request: %s', $this->detail($exception)),
                previous: $exception,
            );
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
        } catch (HttpExceptionInterface $exception) {
            throw new OllamaUnavailableException(
                \sprintf('The Ollama server rejected the request: %s', $this->detail($exception)),
                previous: $exception,
            );
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

    /**
     * Ollama answers 404 with {"error":"model 'x' not found"} - the one line
     * that says what is actually wrong. Dropping it leaves the caller with
     * "could not reach the server", which sends them off to look at the
     * network instead.
     */
    private function detail(HttpExceptionInterface $exception): string
    {
        $response = $exception->getResponse();

        try {
            // false: tresc bledu jest tym, po co tu przyszlismy, wiec
            // getContent() nie ma rzucac drugi raz na tym samym statusie.
            $body = $response->getContent(false);
        } catch (ExceptionInterface) {
            return $exception->getMessage();
        }

        /** @var mixed $decoded */
        $decoded = json_decode($body, true);

        if (\is_array($decoded) && isset($decoded['error']) && \is_string($decoded['error'])) {
            return \sprintf('HTTP %d: %s', $response->getStatusCode(), $decoded['error']);
        }

        return $exception->getMessage();
    }
}
