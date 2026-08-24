<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Chapter;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\ProjectFactory;
use Doctrine\ORM\EntityManagerInterface;

final class SegmentReadTest extends ApiTestCase
{
    public function testListsSegmentsOfAChapterInPosition(): void
    {
        $owner = $this->createUser();
        $chapter = $this->chapterWithSegments($owner, 3);

        $this->request('GET', '/api/chapters/'.$chapter->getId().'/segments', token: $this->authenticate($owner));

        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $payload */
        $payload = $this->payload();

        self::assertCount(3, $payload);
        self::assertSame('Paragraph 0.', $payload[0]['sourceText']);
        self::assertSame('Paragraph 2.', $payload[2]['sourceText']);
    }

    public function testSegmentCarriesTranslationAndStatus(): void
    {
        $owner = $this->createUser();
        $chapter = $this->chapterWithSegments($owner, 1);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $segments = $entityManager->getRepository(Segment::class)->findBy(['chapter' => $chapter]);
        $segments[0]->setTranslatedText('Akapit zero.');
        $segments[0]->setStatus(SegmentStatus::Translated);
        $entityManager->flush();

        $this->request('GET', '/api/chapters/'.$chapter->getId().'/segments', token: $this->authenticate($owner));

        /** @var list<array<string, mixed>> $payload */
        $payload = $this->payload();

        self::assertSame('Akapit zero.', $payload[0]['translatedText']);
        self::assertSame('translated', $payload[0]['status']);
        self::assertArrayHasKey('nodeIndex', $payload[0]);
    }

    public function testReturnsTheWholeChapterInOneResponse(): void
    {
        $owner = $this->createUser();
        $chapter = $this->chapterWithSegments($owner, 150);

        $this->request('GET', '/api/chapters/'.$chapter->getId().'/segments', token: $this->authenticate($owner));

        /** @var list<array<string, mixed>> $payload */
        $payload = $this->payload();

        // Edytor potrzebuje calego rozdzialu naraz: mapuje akapity na wezly
        // podgladu po data-segment-id, a podglad niesie caly tekst rozdzialu.
        self::assertCount(150, $payload);
        self::assertSame('Paragraph 149.', $payload[149]['sourceText']);
    }

    public function testStrangerCannotListSomeoneElsesSegments(): void
    {
        $owner = $this->createUser('owner@example.com');
        $stranger = $this->createUser('stranger@example.com');
        $chapter = $this->chapterWithSegments($owner, 1);

        $this->request('GET', '/api/chapters/'.$chapter->getId().'/segments', token: $this->authenticate($stranger));

        self::assertResponseStatusCodeSame(404);
    }

    public function testListingRequiresAuthentication(): void
    {
        $owner = $this->createUser();
        $chapter = $this->chapterWithSegments($owner, 1);

        $this->request('GET', '/api/chapters/'.$chapter->getId().'/segments');

        self::assertResponseStatusCodeSame(401);
    }

    public function testSegmentCarriesInlineMarkupSafeForThePreview(): void
    {
        $owner = $this->createUser();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        $chapter = new Chapter($project, 0, 'OEBPS/ch1.xhtml', 'Rozdział pierwszy');
        $entityManager->persist($chapter);
        $entityManager->persist(new Segment($chapter, 0, 0, 0, 'A [1]word[/1].', [
            '1' => '<em>',
            '2' => '<a href="ch2.xhtml">',
            '3' => '<span onclick="steal()">',
            '4' => '<script>',
        ]));
        $entityManager->flush();

        $this->request('GET', '/api/chapters/'.$chapter->getId().'/segments', token: $this->authenticate($owner));

        /** @var list<array<string, mixed>> $payload */
        $payload = $this->payload();
        $placeholders = $payload[0]['previewPlaceholders'];

        if (!\is_array($placeholders)) {
            self::fail('previewPlaceholders nie jest mapą.');
        }

        self::assertSame('<em>', $placeholders['1']);
        self::assertSame('<a data-epub-href="ch2.xhtml">', $placeholders['2']);
        self::assertSame('<span>', $placeholders['3']);
        self::assertArrayNotHasKey('4', $placeholders);
    }

    public function testReadsASingleSegment(): void
    {
        $owner = $this->createUser();
        $chapter = $this->chapterWithSegments($owner, 1);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $segments = $entityManager->getRepository(Segment::class)->findBy(['chapter' => $chapter]);

        $this->request('GET', '/api/segments/'.$segments[0]->getId(), token: $this->authenticate($owner));

        self::assertResponseIsSuccessful();

        /** @var array<string, mixed> $payload */
        $payload = $this->payload();

        self::assertSame('Paragraph 0.', $payload['sourceText']);
        self::assertArrayHasKey('previewPlaceholders', $payload);
    }

    public function testStrangerCannotReadASingleSegment(): void
    {
        $owner = $this->createUser('owner@example.com');
        $stranger = $this->createUser('stranger@example.com');
        $chapter = $this->chapterWithSegments($owner, 1);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $segments = $entityManager->getRepository(Segment::class)->findBy(['chapter' => $chapter]);

        $this->request('GET', '/api/segments/'.$segments[0]->getId(), token: $this->authenticate($stranger));

        self::assertResponseStatusCodeSame(404);
    }

    private function chapterWithSegments(User $owner, int $count): Chapter
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        $chapter = new Chapter($project, 0, 'OEBPS/ch1.xhtml', 'Rozdział pierwszy');
        $entityManager->persist($chapter);

        for ($position = 0; $position < $count; ++$position) {
            $entityManager->persist(
                new Segment($chapter, $position, $position, 0, \sprintf('Paragraph %d.', $position), []),
            );
        }

        $entityManager->flush();

        return $chapter;
    }
}
