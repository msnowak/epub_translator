<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Chapter;
use App\Entity\Project;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\ProjectFactory;
use Doctrine\ORM\EntityManagerInterface;

final class ProjectSegmentReadTest extends ApiTestCase
{
    public function testListsOnlyTheFailedParagraphsOfTheWholeBook(): void
    {
        $owner = $this->createUser();
        $project = $this->bookWithTwoChapters($owner);

        $this->request(
            'GET',
            '/api/projects/'.$project->getId().'/segments?status=failed',
            token: $this->authenticate($owner),
        );

        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $payload */
        $payload = $this->payload();

        self::assertCount(2, $payload);
        // Kolejnosc: najpierw rozdzial ze spine'u, potem pozycja w rozdziale.
        // Samo "position" liczy sie od zera w kazdym rozdziale, wiec bez
        // sortowania po rozdziale akapity by sie przeplotly.
        self::assertSame('Rozdział pierwszy', $payload[0]['chapter']['title']);
        self::assertSame('Rozdział drugi', $payload[1]['chapter']['title']);
        self::assertSame('Model nie odpowiedział.', $payload[0]['errorMessage']);
    }

    public function testTheEmbeddedChapterCarriesOnlyWhatALabelNeeds(): void
    {
        $owner = $this->createUser();
        $project = $this->bookWithTwoChapters($owner);

        $this->request(
            'GET',
            '/api/projects/'.$project->getId().'/segments?status=failed',
            token: $this->authenticate($owner),
        );

        /** @var list<array<string, mixed>> $payload */
        $payload = $this->payload();
        $chapter = $payload[0]['chapter'];

        if (!\is_array($chapter)) {
            self::fail('Segment nie niesie rozdziału.');
        }

        self::assertSame(['id', 'spineOrder', 'title'], array_keys($chapter));
    }

    public function testAnUnknownStatusIsRejected(): void
    {
        $owner = $this->createUser();
        $project = $this->bookWithTwoChapters($owner);

        $this->request(
            'GET',
            '/api/projects/'.$project->getId().'/segments?status=zepsute',
            token: $this->authenticate($owner),
        );

        self::assertResponseStatusCodeSame(400);
    }

    public function testTheChapterCollectionTakesTheSameFilter(): void
    {
        $owner = $this->createUser();
        $project = $this->bookWithTwoChapters($owner);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $chapters = $entityManager->getRepository(Chapter::class)->findBy(['project' => $project], ['spineOrder' => 'ASC']);

        $this->request(
            'GET',
            '/api/chapters/'.$chapters[0]->getId().'/segments?status=failed',
            token: $this->authenticate($owner),
        );

        /** @var list<array<string, mixed>> $payload */
        $payload = $this->payload();

        self::assertCount(1, $payload);
        self::assertSame('failed', $payload[0]['status']);
    }

    public function testMoreThanThirtyFailedParagraphsAllComeBackInOneResponse(): void
    {
        // API Platform pagina 30 wynikow domyslnie; ta kolekcja jest jedynym
        // ekranem, ktory pokazuje nieudane akapity, wiec obciecie byloby
        // niewidoczne dla uzytkownika - patrz komentarz przy paginationEnabled
        // w Segment.php.
        $owner = $this->createUser();
        $project = $this->bookWithManyFailedSegments($owner, 35);

        $this->request(
            'GET',
            '/api/projects/'.$project->getId().'/segments?status=failed',
            token: $this->authenticate($owner),
        );

        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $payload */
        $payload = $this->payload();

        self::assertCount(35, $payload);
    }

    public function testStrangerGetsNotFound(): void
    {
        $owner = $this->createUser('owner@example.com');
        $stranger = $this->createUser('stranger@example.com');
        $project = $this->bookWithTwoChapters($owner);

        $this->request(
            'GET',
            '/api/projects/'.$project->getId().'/segments',
            token: $this->authenticate($stranger),
        );

        self::assertResponseStatusCodeSame(404);
    }

    private function bookWithTwoChapters(User $owner): Project
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        foreach ([[0, 'Rozdział pierwszy'], [1, 'Rozdział drugi']] as [$order, $title]) {
            $chapter = new Chapter($project, $order, \sprintf('OEBPS/ch%d.xhtml', $order + 1), $title);
            $entityManager->persist($chapter);

            $good = new Segment($chapter, 0, 0, 0, 'Fine.', []);
            $good->setStatus(SegmentStatus::Translated);
            $entityManager->persist($good);

            $bad = new Segment($chapter, 1, 1, 0, 'Broken.', []);
            $bad->setStatus(SegmentStatus::Failed);
            $bad->setErrorMessage('Model nie odpowiedział.');
            $entityManager->persist($bad);
        }

        $entityManager->flush();

        return $project;
    }

    private function bookWithManyFailedSegments(User $owner, int $count): Project
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        $chapter = new Chapter($project, 0, 'OEBPS/ch1.xhtml', 'Rozdział pierwszy');
        $entityManager->persist($chapter);

        for ($position = 0; $position < $count; ++$position) {
            $segment = new Segment($chapter, $position, $position, 0, \sprintf('Broken %d.', $position), []);
            $segment->setStatus(SegmentStatus::Failed);
            $segment->setErrorMessage('Model nie odpowiedział.');
            $entityManager->persist($segment);
        }

        $entityManager->flush();

        return $project;
    }
}
