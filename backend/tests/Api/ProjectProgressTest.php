<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\EpubBuilder;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ProjectProgressTest extends ApiTestCase
{
    public function testSingleProjectCarriesSegmentCounts(): void
    {
        $token = $this->authenticate($this->createUser());
        $id = $this->upload($token);

        $this->request('GET', '/api/projects/'.$id, token: $token);

        self::assertResponseIsSuccessful();

        /** @var array{totalSegments: int, segmentCounts: array<string, int>} $payload */
        $payload = $this->payload();
        self::assertSame(2, $payload['totalSegments']);
        self::assertSame(2, $payload['segmentCounts']['pending']);
    }

    public function testCollectionStillCarriesSegmentCounts(): void
    {
        $token = $this->authenticate($this->createUser());
        $this->upload($token);

        $this->request('GET', '/api/projects', token: $token);

        self::assertResponseIsSuccessful();

        /** @var list<array{totalSegments: int, segmentCounts: array<string, int>}> $payload */
        $payload = $this->payload();
        self::assertSame(2, $payload[0]['totalSegments']);
        self::assertSame(2, $payload[0]['segmentCounts']['pending']);
    }

    public function testAStrangersProjectStillAnswers404(): void
    {
        $ownerToken = $this->authenticate($this->createUser('owner@example.com'));
        $id = $this->upload($ownerToken);
        $strangerToken = $this->authenticate($this->createUser('stranger@example.com'));

        $this->request('GET', '/api/projects/'.$id, token: $strangerToken);

        self::assertResponseStatusCodeSame(404);
    }

    private function upload(string $token): string
    {
        $path = EpubBuilder::create()->withChapter('ch1.xhtml', '<p>Jeden</p><p>Dwa</p>')->build();

        $this->client->request(
            'POST',
            '/api/projects',
            parameters: ['title' => 'Książka', 'targetLanguage' => 'pl', 'ollamaModel' => 'llama3.1:8b'],
            files: ['file' => new UploadedFile($path, 'book.epub', 'application/epub+zip', test: true)],
            server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );

        self::assertResponseStatusCodeSame(201);

        /** @var array{id: string} $payload */
        $payload = $this->payload();

        return $payload['id'];
    }
}
