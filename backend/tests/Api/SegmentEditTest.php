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

final class SegmentEditTest extends ApiTestCase
{
    public function testManualCorrectionIsSavedAndMarkedEdited(): void
    {
        $owner = $this->createUser();
        $segment = $this->segment($owner, 'This is important.');

        $this->request(
            'PATCH',
            '/api/segments/'.$segment->getId(),
            ['translatedText' => 'To jest naprawdę ważne.'],
            $this->authenticate($owner),
            'application/merge-patch+json',
        );

        self::assertResponseIsSuccessful();
        self::assertSame('To jest naprawdę ważne.', $this->payload()['translatedText']);
        self::assertSame('edited', $this->payload()['status']);
    }

    public function testCorrectionKeepingTokensIsAccepted(): void
    {
        $owner = $this->createUser();
        $segment = $this->segment($owner, 'This is [1]important[/1].', ['1' => '<em>']);

        $this->request(
            'PATCH',
            '/api/segments/'.$segment->getId(),
            ['translatedText' => 'To jest [1]bardzo ważne[/1].'],
            $this->authenticate($owner),
            'application/merge-patch+json',
        );

        self::assertResponseIsSuccessful();
    }

    public function testCorrectionDroppingATokenIsRejected(): void
    {
        $owner = $this->createUser();
        $segment = $this->segment($owner, 'This is [1]important[/1].', ['1' => '<em>']);

        $this->request(
            'PATCH',
            '/api/segments/'.$segment->getId(),
            ['translatedText' => 'To jest ważne.'],
            $this->authenticate($owner),
            'application/merge-patch+json',
        );

        // Bez tego blad wyszedlby dopiero w pobranym pliku - jako zgubiony
        // znacznik formatowania.
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('detail', $this->payload());
    }

    public function testCorrectionInventingATokenIsRejected(): void
    {
        $owner = $this->createUser();
        $segment = $this->segment($owner, 'This is important.');

        $this->request(
            'PATCH',
            '/api/segments/'.$segment->getId(),
            ['translatedText' => 'To jest [1]ważne[/1].'],
            $this->authenticate($owner),
            'application/merge-patch+json',
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function testEmptyCorrectionIsRejected(): void
    {
        $owner = $this->createUser();
        $segment = $this->segment($owner, 'This is important.');

        $this->request(
            'PATCH',
            '/api/segments/'.$segment->getId(),
            ['translatedText' => '   '],
            $this->authenticate($owner),
            'application/merge-patch+json',
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function testStrangerCannotEditSomeoneElsesSegment(): void
    {
        $owner = $this->createUser('owner@example.com');
        $stranger = $this->createUser('stranger@example.com');
        $segment = $this->segment($owner, 'This is important.');

        $this->request(
            'PATCH',
            '/api/segments/'.$segment->getId(),
            ['translatedText' => 'Cudza poprawka.'],
            $this->authenticate($stranger),
            'application/merge-patch+json',
        );

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param array<array-key, string> $placeholders
     */
    private function segment(User $owner, string $sourceText, array $placeholders = []): Segment
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        $chapter = new Chapter($project, 0, 'OEBPS/ch1.xhtml');
        $entityManager->persist($chapter);

        $segment = new Segment($chapter, 0, 0, 0, $sourceText, $placeholders);
        $segment->setStatus(SegmentStatus::Translated);
        $segment->setTranslatedText('Wstępne tłumaczenie.');
        $entityManager->persist($segment);
        $entityManager->flush();

        return $segment;
    }
}
