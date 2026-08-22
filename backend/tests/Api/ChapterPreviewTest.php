<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Chapter;
use App\Entity\Project;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use App\Entity\User;
use App\Storage\ProjectStorage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\EpubBuilder;
use App\Tests\Support\ProjectFactory;
use Doctrine\ORM\EntityManagerInterface;

final class ChapterPreviewTest extends ApiTestCase
{
    public function testReturnsChapterHtmlWithTranslations(): void
    {
        $owner = $this->createUser();
        [$project, $chapter] = $this->projectWithChapter($owner, 'Przetłumaczony akapit.');

        $this->request(
            'GET',
            '/api/projects/'.$project->getId().'/preview/'.$chapter->getId(),
            token: $this->authenticate($owner),
        );

        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('Przetłumaczony akapit.', $html);
        self::assertStringContainsString('data-segment-id=', $html);
    }

    public function testRewritesAssetPathsToTheSignedEndpoint(): void
    {
        $owner = $this->createUser();
        [$project, $chapter] = $this->projectWithChapter($owner, 'Przetłumaczony akapit.');

        $this->request(
            'GET',
            '/api/projects/'.$project->getId().'/preview/'.$chapter->getId(),
            token: $this->authenticate($owner),
        );

        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('/api/projects/'.$project->getId().'/assets/OEBPS/images/cover.png?t=', $html);
    }

    public function testUntranslatedParagraphsFallBackToTheOriginal(): void
    {
        $owner = $this->createUser();
        [$project, $chapter] = $this->projectWithChapter($owner, null);

        $this->request(
            'GET',
            '/api/projects/'.$project->getId().'/preview/'.$chapter->getId(),
            token: $this->authenticate($owner),
        );

        self::assertResponseIsSuccessful();

        // Podglad zawsze pokazuje caly rozdzial - nieprzetlumaczony akapit
        // zostaje w oryginale, zamiast znikac.
        self::assertStringContainsString('A paragraph.', (string) $this->client->getResponse()->getContent());
    }

    public function testStrangerCannotPreviewSomeoneElsesChapter(): void
    {
        $owner = $this->createUser('owner@example.com');
        $stranger = $this->createUser('stranger@example.com');
        [$project, $chapter] = $this->projectWithChapter($owner, 'Przetłumaczony akapit.');

        $this->request(
            'GET',
            '/api/projects/'.$project->getId().'/preview/'.$chapter->getId(),
            token: $this->authenticate($stranger),
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testChapterFromAnotherProjectIsNotFound(): void
    {
        $owner = $this->createUser();
        [$project] = $this->projectWithChapter($owner, 'Przetłumaczony akapit.');
        [, $otherChapter] = $this->projectWithChapter($owner, 'Inny.');

        $this->request(
            'GET',
            '/api/projects/'.$project->getId().'/preview/'.$otherChapter->getId(),
            token: $this->authenticate($owner),
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testPreviewRequiresAuthentication(): void
    {
        $owner = $this->createUser();
        [$project, $chapter] = $this->projectWithChapter($owner, 'Przetłumaczony akapit.');

        $this->request('GET', '/api/projects/'.$project->getId().'/preview/'.$chapter->getId());

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return array{Project, Chapter}
     */
    private function projectWithChapter(User $owner, ?string $translation): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        );

        $epubPath = EpubBuilder::create()
            ->withChapter('ch1.xhtml', '<p>A paragraph.</p><p><img src="images/cover.png"/></p>')
            ->withImage('images/cover.png', $png)
            ->build();

        $storage = self::getContainer()->get(ProjectStorage::class);
        $project->setStoragePath($storage->store(new \SplFileInfo($epubPath), $project));

        $chapter = new Chapter($project, 0, 'OEBPS/ch1.xhtml');
        $entityManager->persist($chapter);

        $segment = new Segment($chapter, 0, 0, 0, 'A paragraph.', []);

        if (null !== $translation) {
            $segment->setTranslatedText($translation);
            $segment->setStatus(SegmentStatus::Translated);
        }

        $entityManager->persist($segment);
        $entityManager->flush();

        return [$project, $chapter];
    }
}
