<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class OpenApiTest extends ApiTestCase
{
    /**
     * @return list<array{string}>
     */
    public static function controlOperations(): array
    {
        return [
            ['/api/projects/{id}/start'],
            ['/api/projects/{id}/pause'],
            ['/api/projects/{id}/resume'],
            ['/api/projects/{id}/cancel'],
            ['/api/projects/{id}/retry-failed'],
            ['/api/segments/{id}/retranslate'],
        ];
    }

    #[DataProvider('controlOperations')]
    public function testControlOperationsDocumentNoRequestBody(string $path): void
    {
        $operation = $this->openApi()['paths'][$path]['post'] ?? null;

        self::assertIsArray($operation, \sprintf('The OpenAPI document has no POST %s.', $path));
        // Te operacje nie maja ciala - maja deserialize: false, a procesor
        // dziala na wczytanej encji. Bez input: false dokument obiecuje
        // czytajacemu schemat zapisu zasobu, ktorego endpoint nie przeczyta.
        self::assertArrayNotHasKey('requestBody', $operation);
    }

    #[DataProvider('controlOperations')]
    public function testControlOperationsSayWhatTheyDo(string $path): void
    {
        $operation = $this->openApi()['paths'][$path]['post'] ?? null;

        self::assertIsArray($operation);

        $summary = $operation['summary'] ?? '';

        self::assertIsString($summary);
        // Domyslny opis API Platform dla operacji Post brzmi "Creates a X
        // resource" - zadna z tych operacji niczego nie tworzy, a front czyta
        // ten dokument jak kontrakt.
        self::assertStringNotContainsString('Creates a', $summary);
        self::assertNotSame('', $summary);
        self::assertNotSame('', $operation['description'] ?? '');
    }

    public function testUploadStillDocumentsItsMultipartBody(): void
    {
        $operation = $this->openApi()['paths']['/api/projects']['post'] ?? null;

        self::assertIsArray($operation);
        self::assertIsArray($operation['requestBody'] ?? null);
        // Upload to jedyny POST z prawdziwym cialem i jest ono formularzem,
        // nie JSON-em.
        self::assertSame(['multipart/form-data'], array_keys($operation['requestBody']['content']));
    }

    public function testTheProjectCollectionAndTheUploadDoNotShareADescription(): void
    {
        $paths = $this->openApi()['paths']['/api/projects'];

        $list = $paths['get']['summary'] ?? '';
        $upload = $paths['post']['summary'] ?? '';

        self::assertIsString($list);
        self::assertIsString($upload);
        // Obie operacje mieszkaja pod tym samym uriTemplate, wiec latwo opisac
        // jedna tekstem drugiej - i tak sie stalo przy pierwszym podejsciu.
        self::assertStringContainsString('Lists', $list);
        self::assertStringContainsString('Uploads', $upload);
    }

    /**
     * @return list<array{string, string}>
     */
    public static function routesOutsideApiPlatform(): array
    {
        return [
            ['/api/projects/{id}/preview/{chapterId}', 'get'],
            ['/api/projects/{id}/assets/{path}', 'get'],
            ['/api/projects/{id}/download', 'get'],
            ['/api/me', 'get'],
            ['/api/token/refresh', 'post'],
            ['/api/token/refresh', 'delete'],
            ['/api/ollama/models', 'get'],
        ];
    }

    #[DataProvider('routesOutsideApiPlatform')]
    public function testPlainControllersAreDocumentedToo(string $path, string $method): void
    {
        $operation = $this->openApi()['paths'][$path][$method] ?? null;

        // Te trasy to zwykle kontrolery Symfony - API Platform nie wie o nich
        // nic, wiec bez dekoratora dokument milczy o polowie API.
        self::assertIsArray($operation, \sprintf('The OpenAPI document has no %s %s.', strtoupper($method), $path));
        self::assertNotSame('', $operation['summary'] ?? '');
    }

    /**
     * @return array{paths: array<string, array<string, array<string, mixed>>>}
     */
    private function openApi(): array
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');

        self::assertResponseIsSuccessful();

        /** @var array{paths: array<string, array<string, array<string, mixed>>>} $document */
        $document = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $document;
    }
}
