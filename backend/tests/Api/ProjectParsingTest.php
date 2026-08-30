<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Repository\ProjectRepository;
use App\Repository\SegmentRepository;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\EpubBuilder;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ProjectParsingTest extends ApiTestCase
{
    public function testUploadedProjectBecomesReadyWithSegments(): void
    {
        $token = $this->authenticate($this->createUser());
        $path = EpubBuilder::create()
            ->withChapter('ch1.xhtml', '<p>Pierwszy.</p><p>Drugi.</p>')
            ->withChapter('ch2.xhtml', '<p>Trzeci.</p>')
            ->build();

        $this->upload($token, $path);

        self::assertResponseStatusCodeSame(201);

        $project = self::getContainer()->get(ProjectRepository::class)->find($this->payload()['id']);
        self::assertNotNull($project);
        self::assertSame('ready', $project->getStatus()->value);

        $counts = self::getContainer()->get(SegmentRepository::class)->countByStatus($project);
        self::assertSame(3, $counts['pending'] ?? 0);
    }

    public function testProjectWithUnreadableStructureEndsAsFailed(): void
    {
        $token = $this->authenticate($this->createUser());

        // Zip z container.xml, ale z pustym manifestem OPF - przechodzi
        // walidacje uploadu, a przewraca sie dopiero przy parsowaniu.
        $path = EpubBuilder::create()->build();

        $this->upload($token, $path);

        self::assertResponseStatusCodeSame(201);

        $project = self::getContainer()->get(ProjectRepository::class)->find($this->payload()['id']);
        self::assertNotNull($project);
        self::assertSame('failed', $project->getStatus()->value);
        self::assertSame(
            'Nie udało się odczytać struktury pliku EPUB. Sprawdź, czy plik nie jest uszkodzony.',
            $project->getErrorMessage(),
        );
    }

    private function upload(string $token, string $path): void
    {
        $this->client->request(
            'POST',
            '/api/projects',
            parameters: [
                'title' => 'Moja książka',
                'targetLanguage' => 'pl',
                'ollamaModel' => 'llama3.1:8b',
            ],
            files: ['file' => new UploadedFile($path, 'book.epub', 'application/epub+zip', test: true)],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );
    }
}
