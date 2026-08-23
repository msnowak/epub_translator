<?php

declare(strict_types=1);

namespace App\Tests\Ollama;

use App\Ollama\OllamaClient;
use App\Ollama\OllamaUnavailableException;
use App\Translation\TranslationRequest;
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

        $client = new OllamaClient($http, 0.2);

        self::assertSame(['llama3.1:8b', 'qwen2.5:7b'], $client->listModels());
    }

    public function testReturnsEmptyListWhenServerHasNoModels(): void
    {
        $http = new MockHttpClient(new MockResponse('{"models":[]}', ['response_headers' => ['content-type' => 'application/json']]));

        self::assertSame([], (new OllamaClient($http, 0.2))->listModels());
    }

    public function testThrowsWhenServerIsUnreachable(): void
    {
        $http = new MockHttpClient(static function (): never {
            throw new \Symfony\Component\HttpClient\Exception\TransportException('Connection refused');
        });

        $this->expectException(OllamaUnavailableException::class);
        // Blad transportu ma nadal mowic o nieosiagalnym serwerze - to jedyny
        // przypadek, w ktorym to prawda.
        $this->expectExceptionMessageMatches('/Could not reach the Ollama server/');

        (new OllamaClient($http, 0.2))->listModels();
    }

    public function testThrowsOnErrorStatusCode(): void
    {
        $http = new MockHttpClient(new MockResponse('unauthorized', ['http_code' => 401]));

        $this->expectException(OllamaUnavailableException::class);

        (new OllamaClient($http, 0.2))->listModels();
    }

    public function testTranslateReturnsMessageContent(): void
    {
        $http = new MockHttpClient(new MockResponse(
            '{"message":{"role":"assistant","content":"To jest [1]ważny[/1] akapit."}}',
            ['response_headers' => ['content-type' => 'application/json']],
        ));

        $translation = (new OllamaClient($http, 0.2))->translate(
            new TranslationRequest('llama3.1:8b', 'system', 'user'),
        );

        self::assertSame('To jest [1]ważny[/1] akapit.', $translation);
    }

    public function testTranslateSendsModelPromptsAndTemperature(): void
    {
        $seen = null;
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? ''];

            return new MockResponse(
                '{"message":{"content":"wynik"}}',
                ['response_headers' => ['content-type' => 'application/json']],
            );
        });

        (new OllamaClient($http, 0.7))->translate(
            new TranslationRequest('qwen2.5:7b', 'jesteś tłumaczem', 'przetłumacz to'),
        );

        self::assertIsArray($seen);
        self::assertSame('POST', $seen['method']);
        self::assertStringEndsWith('/api/chat', $seen['url']);

        /** @var array{model: string, stream: bool, options: array{temperature: float}, messages: list<array{role: string, content: string}>} $body */
        $body = json_decode((string) $seen['body'], true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('qwen2.5:7b', $body['model']);
        self::assertFalse($body['stream']);
        self::assertSame(0.7, $body['options']['temperature']);
        self::assertSame('system', $body['messages'][0]['role']);
        self::assertSame('jesteś tłumaczem', $body['messages'][0]['content']);
        self::assertSame('user', $body['messages'][1]['role']);
        self::assertSame('przetłumacz to', $body['messages'][1]['content']);
    }

    public function testTranslateTurnsTheModelThinkingOff(): void
    {
        $seen = null;
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = $options['body'] ?? '';

            return new MockResponse(
                '{"message":{"content":"wynik"}}',
                ['response_headers' => ['content-type' => 'application/json']],
            );
        });

        (new OllamaClient($http, 0.2))->translate(
            new TranslationRequest('gemma4:12b', 'jesteś tłumaczem', 'przetłumacz to'),
        );

        /** @var array{think: bool} $body */
        $body = json_decode((string) $seen, true, 512, JSON_THROW_ON_ERROR);

        // Model rozumujacy oddaje rozumowanie w polu "thinking", a odpowiedz
        // w "content" - i potrafi wypalic caly budzet tokenow na to pierwsze,
        // zwracajac puste tlumaczenie po dwoch minutach. Tlumaczenie akapitu
        // nie potrzebuje lancucha mysli.
        self::assertFalse($body['think']);
    }

    public function testTranslateThrowsWhenServerIsUnreachable(): void
    {
        $http = new MockHttpClient(static function (): never {
            throw new \Symfony\Component\HttpClient\Exception\TransportException('Connection refused');
        });

        $this->expectException(OllamaUnavailableException::class);

        (new OllamaClient($http, 0.2))->translate(new TranslationRequest('llama3.1:8b', 'system', 'user'));
    }

    public function testTranslateThrowsWhenResponseHasNoContent(): void
    {
        $http = new MockHttpClient(new MockResponse(
            '{"done":true}',
            ['response_headers' => ['content-type' => 'application/json']],
        ));

        $this->expectException(OllamaUnavailableException::class);

        (new OllamaClient($http, 0.2))->translate(new TranslationRequest('llama3.1:8b', 'system', 'user'));
    }

    public function testSaysWhatTheServerRefusedInsteadOfBlamingTheNetwork(): void
    {
        $http = new MockHttpClient(new MockResponse(
            '{"error":"model \'llama3.1:8b\' not found"}',
            ['http_code' => 404, 'response_headers' => ['content-type' => 'application/json']],
        ));

        $this->expectException(OllamaUnavailableException::class);
        // Ollama odpowiada 404 na model, ktorego nie pobrano. Komunikat
        // o nieosiagalnym serwerze wysylalby czytajacego w zla strone.
        $this->expectExceptionMessageMatches("/model 'llama3.1:8b' not found/");

        (new OllamaClient($http, 0.2))->translate(new TranslationRequest('llama3.1:8b', 'system', 'user'));
    }

    public function testTheModelListingReportsTheServerAnswerToo(): void
    {
        $http = new MockHttpClient(new MockResponse(
            '{"error":"something went wrong"}',
            ['http_code' => 500, 'response_headers' => ['content-type' => 'application/json']],
        ));

        $this->expectException(OllamaUnavailableException::class);
        $this->expectExceptionMessageMatches('/something went wrong/');

        (new OllamaClient($http, 0.2))->listModels();
    }
}
