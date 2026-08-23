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

    public function testUploadStillDocumentsItsMultipartBody(): void
    {
        $operation = $this->openApi()['paths']['/api/projects']['post'] ?? null;

        self::assertIsArray($operation);
        self::assertIsArray($operation['requestBody'] ?? null);
        // Upload to jedyny POST z prawdziwym cialem i jest ono formularzem,
        // nie JSON-em.
        self::assertSame(['multipart/form-data'], array_keys($operation['requestBody']['content']));
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
