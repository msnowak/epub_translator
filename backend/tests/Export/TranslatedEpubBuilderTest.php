<?php

declare(strict_types=1);

namespace App\Tests\Export;

use App\Entity\Chapter;
use App\Entity\Project;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use App\Export\TranslatedEpubBuilder;
use App\Storage\ProjectStorage;
use App\Tests\Support\EpubBuilder;
use App\Tests\Support\ProjectFactory;
use App\Tests\Support\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TranslatedEpubBuilderTest extends KernelTestCase
{
    public function testPutsTheTranslationIntoTheChapter(): void
    {
        $project = $this->project('Przetłumaczony akapit.', SegmentStatus::Translated);

        $zip = $this->build($project);

        $chapter = (string) $zip->getFromName('OEBPS/ch1.xhtml');

        self::assertStringContainsString('Przetłumaczony akapit.', $chapter);
        self::assertStringNotContainsString('A paragraph.', $chapter);

        $zip->close();
    }

    public function testKeepsAFailedParagraphInTheOriginal(): void
    {
        $project = $this->project('Nieudane tłumaczenie.', SegmentStatus::Failed);

        $zip = $this->build($project);

        // Akapit, ktorego nie udalo sie przetlumaczyc, zostaje po angielsku -
        // ta sama regula, ktora rzadzi podgladem. Plik ma sie otworzyc.
        $chapter = (string) $zip->getFromName('OEBPS/ch1.xhtml');

        self::assertStringContainsString('A paragraph.', $chapter);
        self::assertStringNotContainsString('Nieudane tłumaczenie.', $chapter);

        $zip->close();
    }

    public function testEachChapterGetsItsOwnTranslation(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = UserFactory::create($entityManager, $container->get(UserPasswordHasherInterface::class));
        $project = ProjectFactory::create($entityManager, $user);

        $epubPath = EpubBuilder::create()
            ->withLanguage('en')
            ->withChapter('ch1.xhtml', '<p>First paragraph.</p>')
            ->withChapter('ch2.xhtml', '<p>Second paragraph.</p>')
            ->build();

        $project->setStoragePath($container->get(ProjectStorage::class)->store(new \SplFileInfo($epubPath), $project));

        $chapter1 = new Chapter($project, 0, 'OEBPS/ch1.xhtml');
        $chapter2 = new Chapter($project, 1, 'OEBPS/ch2.xhtml');
        $entityManager->persist($chapter1);
        $entityManager->persist($chapter2);

        $segment1 = new Segment($chapter1, 0, 0, 0, 'First paragraph.', []);
        $segment1->setStatus(SegmentStatus::Translated);
        $segment1->setTranslatedText('Pierwszy akapit.');

        $segment2 = new Segment($chapter2, 0, 0, 0, 'Second paragraph.', []);
        $segment2->setStatus(SegmentStatus::Translated);
        $segment2->setTranslatedText('Drugi akapit.');

        $entityManager->persist($segment1);
        $entityManager->persist($segment2);
        $entityManager->flush();

        $zip = $this->build($project);

        $ch1 = (string) $zip->getFromName('OEBPS/ch1.xhtml');
        $ch2 = (string) $zip->getFromName('OEBPS/ch2.xhtml');

        // Kazdy rozdzial ma dostac wlasne tlumaczenie - petla po
        // findForProjectInSpineOrder() nie moze wpisac tej samej tresci
        // dwa razy pod dwoma roznymi kluczami mapy $documents.
        self::assertStringContainsString('Pierwszy akapit.', $ch1);
        self::assertStringNotContainsString('Drugi akapit.', $ch1);

        self::assertStringContainsString('Drugi akapit.', $ch2);
        self::assertStringNotContainsString('Pierwszy akapit.', $ch2);

        $zip->close();
    }

    public function testUpdatesTheLanguageAndTheTitle(): void
    {
        $project = $this->project('Przetłumaczony akapit.', SegmentStatus::Translated);

        $zip = $this->build($project);

        $opf = (string) $zip->getFromName('OEBPS/content.opf');

        self::assertStringContainsString('<dc:language>pl</dc:language>', $opf);
        self::assertStringContainsString('<dc:title>Testowa książka</dc:title>', $opf);

        $zip->close();
    }

    public function testLeavesTheBookResourcesUntouched(): void
    {
        $project = $this->project('Przetłumaczony akapit.', SegmentStatus::Translated);

        $zip = $this->build($project);

        self::assertSame($this->png(), $zip->getFromName('OEBPS/images/cover.png'));
        self::assertNotFalse($zip->getFromName('META-INF/container.xml'));

        $first = $zip->statIndex(0);

        if (false === $first) {
            self::fail('The exported archive has no entries.');
        }

        self::assertSame('mimetype', $first['name']);

        $zip->close();
    }

    private function build(Project $project): \ZipArchive
    {
        $path = self::getContainer()->get(TranslatedEpubBuilder::class)->build($project);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path));

        return $zip;
    }

    private function project(?string $translation, SegmentStatus $status): Project
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $user = UserFactory::create($entityManager, $container->get(UserPasswordHasherInterface::class));
        $project = ProjectFactory::create($entityManager, $user);

        $epubPath = EpubBuilder::create()
            ->withLanguage('en')
            ->withChapter('ch1.xhtml', '<p>A paragraph.</p><p><img src="images/cover.png"/></p>')
            ->withImage('images/cover.png', $this->png())
            ->build();

        $project->setStoragePath($container->get(ProjectStorage::class)->store(new \SplFileInfo($epubPath), $project));

        $chapter = new Chapter($project, 0, 'OEBPS/ch1.xhtml');
        $entityManager->persist($chapter);

        $segment = new Segment($chapter, 0, 0, 0, 'A paragraph.', []);
        $segment->setStatus($status);

        if (null !== $translation) {
            // Tekst siedzi w segmencie takze przy statusie failed - o tym, czy
            // wroci do ksiazki, decyduje status, nie obecnosc tlumaczenia.
            $segment->setTranslatedText($translation);
        }

        $entityManager->persist($segment);
        $entityManager->flush();

        return $project;
    }

    private function png(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
    }
}
