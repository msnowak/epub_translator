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

    public function testMintedUrlForAnAssetWithASpaceIsFetchable(): void
    {
        $owner = $this->createUser();
        [$project, $chapter] = $this->projectWithEncodedAssetHref($owner);

        $this->request(
            'GET',
            '/api/projects/'.$project->getId().'/preview/'.$chapter->getId(),
            token: $this->authenticate($owner),
        );

        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();

        self::assertSame(1, preg_match('#"(/api/projects/[^"]+/assets/[^"]+)"#', $html, $matches));

        // Ksiazka zapisuje spacje procentowo, a router dekoduje sciezke przed
        // dopasowaniem trasy - podpis musi trafic w postac zdekodowana,
        // inaczej wlasny adres podgladu dostaje 403.
        $this->client->request('GET', $matches[1] ?? '');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
    }

    public function testMarkupOnlyTranslationDoesNotShiftTheFollowingSegmentId(): void
    {
        $owner = $this->createUser();
        [$project, $chapter, $second] = $this->projectWithMarkupOnlyTranslation($owner);

        $this->request(
            'GET',
            '/api/projects/'.$project->getId().'/preview/'.$chapter->getId(),
            token: $this->authenticate($owner),
        );

        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();

        // Pierwszy akapit po zlozeniu nie ma juz tekstu. Gdyby podglad liczyl
        // bloki drugi raz, "seg-two" wyladowaloby na nim, a edytor pisalby
        // poprawki do zlego wiersza.
        self::assertMatchesRegularExpression(
            '/<p data-segment-id="'.preg_quote((string) $second->getId(), '/').'">Drugie\./',
            $html,
        );
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

    public function testSplitParagraphGetsExactlyOneSegmentIdFromItsFirstSubSegment(): void
    {
        $owner = $this->createUser();
        [$project, $chapter, $firstSubSegment] = $this->projectWithSplitParagraph($owner);

        $this->request(
            'GET',
            '/api/projects/'.$project->getId().'/preview/'.$chapter->getId(),
            token: $this->authenticate($owner),
        );

        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();

        // Oba podsegmenty skladaja sie na jeden blok - powinien dostac
        // dokladnie jedno "data-segment-id", i to od pierwszego podsegmentu.
        self::assertSame(1, substr_count($html, 'data-segment-id='));
        self::assertStringContainsString('data-segment-id="'.$firstSubSegment->getId().'"', $html);
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

    public function testPreviewCarriesItsOwnContentPolicy(): void
    {
        $owner = $this->createUser();
        [$project, $chapter] = $this->projectWithChapter($owner, 'Przetłumaczony akapit.');

        $this->request(
            'GET',
            '/api/projects/'.$project->getId().'/preview/'.$chapter->getId(),
            token: $this->authenticate($owner),
        );

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');

        $policy = (string) $this->client->getResponse()->headers->get('Content-Security-Policy');

        // Polityka zasobow ("default-src 'none'; sandbox") zabilaby podgladowi
        // wlasne obrazy i style, wiec ta jest skrojona - ale skrypt z ksiazki
        // nadal nie ma sie jak wykonac.
        self::assertStringContainsString("default-src 'none'", $policy);
        self::assertStringContainsString("img-src 'self' data:", $policy);
        self::assertStringContainsString("style-src 'self' 'unsafe-inline'", $policy);
        self::assertStringContainsString('sandbox allow-same-origin', $policy);
        self::assertStringNotContainsString('script-src', $policy);
    }

    public function testTheRenderedChapterCarriesThePolicyInItsBody(): void
    {
        $owner = $this->createUser();
        [$project, $chapter] = $this->projectWithChapter($owner, 'Przetłumaczony akapit.');

        $this->request(
            'GET',
            '/api/projects/'.$project->getId().'/preview/'.$chapter->getId(),
            token: $this->authenticate($owner),
        );

        self::assertResponseIsSuccessful();

        $body = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('http-equiv="Content-Security-Policy"', $body);
        self::assertStringNotContainsString('xmlns=""', $body);
    }

    public function testTheContentPolicyIsAlsoOnTheErrorResponse(): void
    {
        $owner = $this->createUser('owner@example.com');
        $stranger = $this->createUser('stranger@example.com');
        [$project, $chapter] = $this->projectWithChapter($owner, 'Przetłumaczony akapit.');

        $this->request(
            'GET',
            '/api/projects/'.$project->getId().'/preview/'.$chapter->getId(),
            token: $this->authenticate($stranger),
        );

        // Sciezka wyjscia z kontrolera nie moze decydowac o naglowkach -
        // tak samo jak w ProjectAssetController.
        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertNotNull($this->client->getResponse()->headers->get('Content-Security-Policy'));
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

    /**
     * @return array{Project, Chapter}
     */
    private function projectWithEncodedAssetHref(User $owner): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        );

        $epubPath = EpubBuilder::create()
            ->withChapter('ch1.xhtml', '<p><img src="images/my%20image.png"/></p><p>A paragraph.</p>')
            ->withImage('images/my image.png', $png, 'images/my%20image.png')
            ->build();

        $storage = self::getContainer()->get(ProjectStorage::class);
        $project->setStoragePath($storage->store(new \SplFileInfo($epubPath), $project));

        $chapter = new Chapter($project, 0, 'OEBPS/ch1.xhtml');
        $entityManager->persist($chapter);
        $entityManager->flush();

        return [$project, $chapter];
    }

    /**
     * @return array{Project, Chapter, Segment}
     */
    private function projectWithMarkupOnlyTranslation(User $owner): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        $epubPath = EpubBuilder::create()
            ->withChapter('ch1.xhtml', '<p><em>Foo</em></p><p>Second.</p>')
            ->build();

        $storage = self::getContainer()->get(ProjectStorage::class);
        $project->setStoragePath($storage->store(new \SplFileInfo($epubPath), $project));

        $chapter = new Chapter($project, 0, 'OEBPS/ch1.xhtml');
        $entityManager->persist($chapter);

        // Tlumaczenie samych zetonow przechodzi walidacje, a sklada sie do
        // "<em></em>" - blok bez tekstu, ktory drugie wyliczenie blokow
        // by pominelo.
        $first = new Segment($chapter, 0, 0, 0, '[1]Foo[/1]', ['1' => '<em>']);
        $first->setTranslatedText('[1][/1]');
        $first->setStatus(SegmentStatus::Translated);

        $second = new Segment($chapter, 1, 1, 0, 'Second.', []);
        $second->setTranslatedText('Drugie.');
        $second->setStatus(SegmentStatus::Translated);

        $entityManager->persist($first);
        $entityManager->persist($second);
        $entityManager->flush();

        return [$project, $chapter, $second];
    }

    /**
     * @return array{Project, Chapter, Segment}
     */
    private function projectWithSplitParagraph(User $owner): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $project = ProjectFactory::create($entityManager, $owner);

        $epubPath = EpubBuilder::create()
            ->withChapter('ch1.xhtml', '<p>First sentence. Second sentence.</p>')
            ->build();

        $storage = self::getContainer()->get(ProjectStorage::class);
        $project->setStoragePath($storage->store(new \SplFileInfo($epubPath), $project));

        $chapter = new Chapter($project, 0, 'OEBPS/ch1.xhtml');
        $entityManager->persist($chapter);

        // Jeden blok, dwa podsegmenty tego samego nodeIndex - dokladnie
        // przypadek, ktory segmentIdsByNodeIndex() ma zdedupikowac.
        $first = new Segment($chapter, 0, 0, 0, 'First sentence.', []);
        $first->setTranslatedText('Pierwsze zdanie.');
        $first->setStatus(SegmentStatus::Translated);

        $second = new Segment($chapter, 1, 0, 1, 'Second sentence.', []);
        $second->setTranslatedText('Drugie zdanie.');
        $second->setStatus(SegmentStatus::Translated);

        $entityManager->persist($first);
        $entityManager->persist($second);
        $entityManager->flush();

        return [$project, $chapter, $first];
    }
}
