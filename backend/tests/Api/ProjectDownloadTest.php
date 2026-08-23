<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Chapter;
use App\Entity\Project;
use App\Entity\ProjectStatus;
use App\Entity\Segment;
use App\Entity\SegmentStatus;
use App\Entity\User;
use App\Storage\ProjectStorage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\EpubBuilder;
use App\Tests\Support\ProjectFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ProjectDownloadTest extends ApiTestCase
{
    public function testDownloadsABookWithTheTranslationInIt(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithChapter($owner, 'Przetłumaczony akapit.', SegmentStatus::Translated);

        $this->download($project, $owner);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/epub+zip');

        $zip = $this->archive();

        self::assertStringContainsString('Przetłumaczony akapit.', (string) $zip->getFromName('OEBPS/ch1.xhtml'));
        self::assertStringContainsString('<dc:language>pl</dc:language>', (string) $zip->getFromName('OEBPS/content.opf'));
        // Obraz z ksiazki przechodzi bajt w bajt.
        self::assertSame($this->png(), $zip->getFromName('OEBPS/images/cover.png'));

        $zip->close();
    }

    public function testAFailedParagraphComesBackInTheOriginal(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithChapter($owner, 'Nieudane tłumaczenie.', SegmentStatus::Failed);

        $this->download($project, $owner);

        self::assertResponseIsSuccessful();

        $zip = $this->archive();
        $chapter = (string) $zip->getFromName('OEBPS/ch1.xhtml');

        // Segment ma tekst, ale ma tez status failed - o tym, co wchodzi do
        // ksiazki, decyduje status. Plik ma sie otworzyc zawsze, a polowa
        // po polsku bylaby gorsza niz akapit, ktory zostal po angielsku.
        self::assertStringContainsString('A paragraph.', $chapter);
        self::assertStringNotContainsString('Nieudane tłumaczenie.', $chapter);

        $zip->close();
    }

    public function testTheFileNameCarriesTheTitleAndTheLanguage(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithChapter($owner, 'Przetłumaczony akapit.', SegmentStatus::Translated);

        $this->download($project, $owner);

        $disposition = (string) $this->client->getResponse()->headers->get('Content-Disposition');

        self::assertStringStartsWith('attachment;', $disposition);
        // Tytul bywa cyrylica, wiec obok wersji UTF-8 idzie fallback ASCII.
        self::assertStringContainsString("filename*=utf-8''", $disposition);
        self::assertStringContainsString('.epub', $disposition);
    }

    public function testATitleWithASlashStillDownloads(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithChapter(
            $owner,
            'Przetłumaczony akapit.',
            SegmentStatus::Translated,
            title: 'Rok 1984 / Folwark zwierzęcy',
        );

        $this->download($project, $owner);

        // HeaderUtils::makeDisposition() rzuca, gdy filename albo fallback
        // niesie "/" lub "\" - a tytul projektu nie ma takiego ograniczenia.
        // Przed poprawka to walilo sie 500-tka i zostawialo zbudowana kopie
        // ksiazki osierocona w katalogu tymczasowym.
        self::assertResponseIsSuccessful();

        $disposition = (string) $this->client->getResponse()->headers->get('Content-Disposition');

        self::assertStringNotContainsString('/', $disposition);
        self::assertStringNotContainsString('\\', $disposition);
    }

    public function testTheTemporaryFileIsGoneOnceTheDownloadHasBeenSent(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithChapter($owner, 'Przetłumaczony akapit.', SegmentStatus::Translated);

        $this->download($project, $owner);

        $response = $this->client->getResponse();

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        // Klient testowy wystrumieniowal odpowiedz, wiec deleteFileAfterSend
        // juz zadzialal - inaczej kazde pobranie zostawialoby kopie ksiazki
        // w katalogu tymczasowym.
        self::assertFileDoesNotExist($response->getFile()->getPathname());
    }

    public function testABookCanBeDownloadedWhileTheTranslationIsStillRunning(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithChapter($owner, null, SegmentStatus::Pending, ProjectStatus::Translating);

        $this->download($project, $owner);

        // Sprawdzenie eksportu na wlasnym czytniku nie moze wymagac
        // przemielenia calego tomu.
        self::assertResponseIsSuccessful();
    }

    public function testAProjectStillBeingParsedHasNothingToDownload(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithChapter($owner, null, SegmentStatus::Pending, ProjectStatus::Parsing);

        $this->download($project, $owner);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('application/problem+json', $this->client->getResponse()->headers->get('Content-Type'));
    }

    public function testAProjectWhoseStoredFileIsUnreadableGets404(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithChapter($owner, 'Przetłumaczony akapit.', SegmentStatus::Translated);

        // Nadpisujemy plik po store() - projekt ma status Completed i status
        // pozwala pobierac, ale sam plik na dysku przestal byc czytelnym
        // EPUB-em. TranslatedEpubBuilder::build() rzuci InvalidEpubException.
        file_put_contents((string) $project->getStoragePath(), 'to nie jest archiwum zip');

        $this->download($project, $owner);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('application/problem+json', $this->client->getResponse()->headers->get('Content-Type'));
    }

    public function testSetsANosniffHeaderLikeTheOtherBookEndpoints(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithChapter($owner, 'Przetłumaczony akapit.', SegmentStatus::Translated);

        $this->download($project, $owner);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
    }

    public function testAFailedProjectHasNothingToDownload(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithChapter($owner, null, SegmentStatus::Pending, ProjectStatus::Failed);

        $this->download($project, $owner);

        self::assertResponseStatusCodeSame(409);
    }

    public function testStrangerCannotDownloadSomeoneElsesBook(): void
    {
        $owner = $this->createUser('owner@example.com');
        $stranger = $this->createUser('stranger@example.com');
        $project = $this->projectWithChapter($owner, 'Przetłumaczony akapit.', SegmentStatus::Translated);

        $this->download($project, $stranger);

        // 404, nie 403: identyfikator nie ma potwierdzac istnienia projektu.
        self::assertResponseStatusCodeSame(404);
    }

    public function testDownloadRequiresAuthentication(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithChapter($owner, 'Przetłumaczony akapit.', SegmentStatus::Translated);

        $this->request('GET', '/api/projects/'.$project->getId().'/download');

        self::assertResponseStatusCodeSame(401);
    }

    private function download(Project $project, User $user): void
    {
        $this->request(
            'GET',
            '/api/projects/'.$project->getId().'/download',
            token: $this->authenticate($user),
        );
    }

    private function archive(): \ZipArchive
    {
        // Klient testowy wola sendContent() przy filtrowaniu odpowiedzi, wiec
        // BinaryFileResponse zdazyl juz wystrumieniowac plik i - zgodnie
        // z deleteFileAfterSend - skasowac go z dysku. Bajty sa w odpowiedzi
        // BrowserKita; getContent() na samym BinaryFileResponse zwraca false.
        $bytes = $this->client->getInternalResponse()->getContent();

        self::assertNotSame('', $bytes);

        $path = tempnam(sys_get_temp_dir(), 'downloaded');

        if (false === $path) {
            self::fail('Could not create a temporary file.');
        }

        file_put_contents($path, $bytes);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($path));

        return $zip;
    }

    private function projectWithChapter(
        User $owner,
        ?string $translation,
        SegmentStatus $segmentStatus,
        ProjectStatus $projectStatus = ProjectStatus::Completed,
        string $title = 'Testowa książka',
    ): Project {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner, $title, $projectStatus);

        $epubPath = EpubBuilder::create()
            ->withLanguage('en')
            ->withChapter('ch1.xhtml', '<p>A paragraph.</p><p><img src="images/cover.png"/></p>')
            ->withImage('images/cover.png', $this->png())
            ->build();

        $storage = self::getContainer()->get(ProjectStorage::class);
        $project->setStoragePath($storage->store(new \SplFileInfo($epubPath), $project));

        $chapter = new Chapter($project, 0, 'OEBPS/ch1.xhtml');
        $entityManager->persist($chapter);

        $segment = new Segment($chapter, 0, 0, 0, 'A paragraph.', []);
        $segment->setStatus($segmentStatus);

        if (null !== $translation) {
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
