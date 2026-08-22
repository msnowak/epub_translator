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

final class ChapterReadTest extends ApiTestCase
{
    public function testListsChaptersInSpineOrder(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithChapters($owner);

        $this->request('GET', '/api/projects/'.$project->getId().'/chapters', token: $this->authenticate($owner));

        self::assertResponseIsSuccessful();

        /** @var list<array<string, mixed>> $payload */
        $payload = $this->payload();

        self::assertCount(2, $payload);
        self::assertSame(0, $payload[0]['spineOrder']);
        self::assertSame(1, $payload[1]['spineOrder']);
    }

    public function testEachChapterCarriesItsOwnProgress(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithChapters($owner);

        $this->request('GET', '/api/projects/'.$project->getId().'/chapters', token: $this->authenticate($owner));

        /** @var list<array<string, mixed>> $payload */
        $payload = $this->payload();

        // Pierwszy rozdzial: jeden przetlumaczony, jeden oczekujacy.
        self::assertSame(2, $payload[0]['totalSegments']);
        self::assertSame(1, $payload[0]['segmentCounts']['translated']);
        self::assertSame(1, $payload[0]['segmentCounts']['pending']);

        // Drugi rozdzial: jeden oczekujacy.
        self::assertSame(1, $payload[1]['totalSegments']);
    }

    public function testCountsDoNotLeakBetweenChapters(): void
    {
        $owner = $this->createUser();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        $first = new Chapter($project, 0, 'OEBPS/ch1.xhtml', 'Rozdział pierwszy');
        $second = new Chapter($project, 1, 'OEBPS/ch2.xhtml', 'Rozdział drugi');
        $third = new Chapter($project, 2, 'OEBPS/ch3.xhtml', 'Rozdział trzeci');
        $entityManager->persist($first);
        $entityManager->persist($second);
        $entityManager->persist($third);

        // Kazdy rozdzial ma inny status i inna liczbe segmentow - gdyby
        // zgrupowane zapytanie pomylilo rozdzialy albo zwrocilo te same
        // liczniki dla kazdego z nich, ponizsze asercje by to wykryly.
        $t1 = new Segment($first, 0, 0, 0, 'One.', []);
        $t1->setStatus(SegmentStatus::Translated);
        $t2 = new Segment($first, 1, 1, 0, 'Two.', []);
        $t2->setStatus(SegmentStatus::Translated);
        $entityManager->persist($t1);
        $entityManager->persist($t2);

        $failed = new Segment($second, 0, 0, 0, 'Three.', []);
        $failed->setStatus(SegmentStatus::Failed);
        $entityManager->persist($failed);

        $entityManager->persist(new Segment($third, 0, 0, 0, 'Four.', []));
        $entityManager->persist(new Segment($third, 1, 1, 0, 'Five.', []));
        $entityManager->persist(new Segment($third, 2, 2, 0, 'Six.', []));

        $entityManager->flush();

        $this->request('GET', '/api/projects/'.$project->getId().'/chapters', token: $this->authenticate($owner));

        /** @var list<array<string, mixed>> $payload */
        $payload = $this->payload();

        self::assertCount(3, $payload);

        self::assertSame(['translated' => 2], $payload[0]['segmentCounts']);
        self::assertSame(2, $payload[0]['totalSegments']);

        self::assertSame(['failed' => 1], $payload[1]['segmentCounts']);
        self::assertSame(1, $payload[1]['totalSegments']);

        self::assertSame(['pending' => 3], $payload[2]['segmentCounts']);
        self::assertSame(3, $payload[2]['totalSegments']);
    }

    public function testStrangerCannotListSomeoneElsesChapters(): void
    {
        $owner = $this->createUser('owner@example.com');
        $stranger = $this->createUser('stranger@example.com');
        $project = $this->projectWithChapters($owner);

        $this->request('GET', '/api/projects/'.$project->getId().'/chapters', token: $this->authenticate($stranger));

        self::assertResponseStatusCodeSame(404);
    }

    public function testListingRequiresAuthentication(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithChapters($owner);

        $this->request('GET', '/api/projects/'.$project->getId().'/chapters');

        self::assertResponseStatusCodeSame(401);
    }

    private function projectWithChapters(User $owner): Project
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        // Celowo w odwrotnej kolejnosci - endpoint ma sortowac po spineOrder.
        $second = new Chapter($project, 1, 'OEBPS/ch2.xhtml', 'Rozdział drugi');
        $first = new Chapter($project, 0, 'OEBPS/ch1.xhtml', 'Rozdział pierwszy');
        $entityManager->persist($second);
        $entityManager->persist($first);

        $translated = new Segment($first, 0, 0, 0, 'One.', []);
        $translated->setTranslatedText('Jeden.');
        $translated->setStatus(SegmentStatus::Translated);
        $entityManager->persist($translated);
        $entityManager->persist(new Segment($first, 1, 1, 0, 'Two.', []));
        $entityManager->persist(new Segment($second, 0, 0, 0, 'Three.', []));

        $entityManager->flush();

        return $project;
    }
}
