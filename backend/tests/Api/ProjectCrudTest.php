<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Repository\ProjectRepository;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\EpubBuilder;
use App\Tests\Support\ProjectFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ProjectCrudTest extends ApiTestCase
{
    public function testCollectionCarriesSegmentCounts(): void
    {
        $token = $this->authenticate($this->createUser());
        $path = EpubBuilder::create()->withChapter('ch1.xhtml', '<p>Jeden</p><p>Dwa</p>')->build();

        $this->client->request(
            'POST',
            '/api/projects',
            parameters: ['title' => 'Książka', 'targetLanguage' => 'pl', 'ollamaModel' => 'llama3.1:8b'],
            files: ['file' => new UploadedFile($path, 'book.epub', 'application/epub+zip', test: true)],
            server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );

        $this->request('GET', '/api/projects', token: $token);

        self::assertResponseIsSuccessful();

        /** @var list<array{totalSegments: int, segmentCounts: array<string, int>}> $payload */
        $payload = $this->payload();
        $project = $payload[0];
        self::assertSame(2, $project['totalSegments']);
        self::assertSame(2, $project['segmentCounts']['pending']);
    }

    public function testOwnerCanEditProjectSettings(): void
    {
        $owner = $this->createUser();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        $this->request(
            'PATCH',
            '/api/projects/'.$project->getId(),
            ['targetLanguage' => 'de', 'customPrompt' => 'Zachowaj styl formalny.'],
            $this->authenticate($owner),
            'application/merge-patch+json',
        );

        self::assertResponseIsSuccessful();
        self::assertSame('de', $this->payload()['targetLanguage']);
        self::assertSame('Zachowaj styl formalny.', $this->payload()['customPrompt']);
    }

    public function testStrangerCannotEditSomeoneElsesProject(): void
    {
        $owner = $this->createUser('owner@example.com');
        $stranger = $this->createUser('stranger@example.com');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        $this->request(
            'PATCH',
            '/api/projects/'.$project->getId(),
            ['targetLanguage' => 'de'],
            $this->authenticate($stranger),
            'application/merge-patch+json',
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testStrangerCannotDeleteSomeoneElsesProject(): void
    {
        $owner = $this->createUser('owner@example.com');
        $stranger = $this->createUser('stranger@example.com');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        $this->request('DELETE', '/api/projects/'.$project->getId(), token: $this->authenticate($stranger));

        self::assertResponseStatusCodeSame(404);
        self::assertNotNull(self::getContainer()->get(ProjectRepository::class)->find($project->getId()));
    }

    public function testDeleteRemovesProjectAndItsFiles(): void
    {
        $token = $this->authenticate($this->createUser());
        $path = EpubBuilder::create()->withChapter('ch1.xhtml', '<p>Jeden</p>')->build();

        $this->client->request(
            'POST',
            '/api/projects',
            parameters: ['title' => 'Książka', 'targetLanguage' => 'pl', 'ollamaModel' => 'llama3.1:8b'],
            files: ['file' => new UploadedFile($path, 'book.epub', 'application/epub+zip', test: true)],
            server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );

        $id = $this->payload()['id'];
        $repository = self::getContainer()->get(ProjectRepository::class);
        $storagePath = $repository->find($id)?->getStoragePath();
        self::assertNotNull($storagePath);
        self::assertFileExists($storagePath);

        $this->request('DELETE', '/api/projects/'.$id, token: $token);

        self::assertResponseStatusCodeSame(204);
        self::assertFileDoesNotExist($storagePath);
    }
}
