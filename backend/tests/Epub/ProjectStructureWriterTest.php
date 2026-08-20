<?php

declare(strict_types=1);

namespace App\Tests\Epub;

use App\Entity\SegmentStatus;
use App\Epub\ProjectStructureWriter;
use App\Repository\ChapterRepository;
use App\Repository\SegmentRepository;
use App\Tests\Support\EpubBuilder;
use App\Tests\Support\ProjectFactory;
use App\Tests\Support\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProjectStructureWriterTest extends KernelTestCase
{
    public function testWritesChaptersAndSegments(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = UserFactory::create($entityManager, $container->get(UserPasswordHasherInterface::class));
        $project = ProjectFactory::create($entityManager, $user);

        $path = EpubBuilder::create()
            ->withChapter('ch1.xhtml', '<p>Pierwszy akapit.</p><p>Drugi <em>akapit</em>.</p>')
            ->withChapter('ch2.xhtml', '<h1>Rozdział</h1>')
            ->build();

        $created = $container->get(ProjectStructureWriter::class)->write($project, $path);

        self::assertSame(3, $created);

        $chapters = $container->get(ChapterRepository::class)->findBy(['project' => $project], ['spineOrder' => 'ASC']);
        self::assertCount(2, $chapters);
        self::assertSame('OEBPS/ch1.xhtml', $chapters[0]->getHref());

        $segments = $container->get(SegmentRepository::class)->findBy(['chapter' => $chapters[0]], ['position' => 'ASC']);
        self::assertCount(2, $segments);
        self::assertSame('Pierwszy akapit.', $segments[0]->getSourceText());
        self::assertSame('Drugi [1]akapit[/1].', $segments[1]->getSourceText());
        self::assertSame(['1' => '<em>'], $segments[1]->getPlaceholders());
        self::assertSame(SegmentStatus::Pending, $segments[0]->getStatus());
    }

    public function testCountsSegmentsByStatus(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = UserFactory::create($entityManager, $container->get(UserPasswordHasherInterface::class));
        $project = ProjectFactory::create($entityManager, $user);

        $path = EpubBuilder::create()->withChapter('ch1.xhtml', '<p>Jeden</p><p>Dwa</p>')->build();
        $container->get(ProjectStructureWriter::class)->write($project, $path);

        $counts = $container->get(SegmentRepository::class)->countByStatus($project);

        self::assertSame(2, $counts['pending'] ?? 0);
    }
}
