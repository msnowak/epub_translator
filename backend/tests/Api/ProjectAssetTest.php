<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Project;
use App\Entity\User;
use App\Preview\AssetUrlSigner;
use App\Storage\ProjectStorage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\EpubBuilder;
use App\Tests\Support\ProjectFactory;
use Doctrine\ORM\EntityManagerInterface;

final class ProjectAssetTest extends ApiTestCase
{
    public function testStreamsAnAssetFromInsideTheBook(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithFile($owner);
        $url = $this->signedUrl($project, 'OEBPS/images/cover.png');

        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
    }

    public function testHardensEveryAssetResponse(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithFile($owner);

        $this->client->request('GET', $this->signedUrl($project, 'OEBPS/images/cover.png'));

        // Bajty pochodza wprost z ksiazki i nie przechodza przez odkazanie
        // podgladu, wiec przegladarka nie moze zgadywac ich typu ani pozwolic
        // im na cokolwiek na naszej domenie.
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Content-Security-Policy', "default-src 'none'; sandbox");

        $this->client->request('GET', '/api/projects/'.$project->getId().'/assets/OEBPS/images/cover.png');

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Content-Security-Policy', "default-src 'none'; sandbox");
    }

    public function testServesChapterDocumentsAsPlainText(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithFile($owner);

        $this->client->request('GET', $this->signedUrl($project, 'OEBPS/ch1.xhtml'));

        self::assertResponseIsSuccessful();

        // Rozdzial jest w manifescie, wiec da sie o niego poprosic - ale jako
        // dokument wykonalby swoje skrypty na naszej domenie.
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=utf-8');
    }

    public function testRefusesAnUnsignedRequest(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithFile($owner);

        $this->client->request('GET', '/api/projects/'.$project->getId().'/assets/OEBPS/images/cover.png');

        self::assertResponseStatusCodeSame(403);
    }

    public function testRefusesATamperedSignature(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithFile($owner);
        $url = $this->signedUrl($project, 'OEBPS/images/cover.png');

        $this->client->request('GET', str_replace('OEBPS/images/cover.png', 'OEBPS/ch1.xhtml', $url));

        self::assertResponseStatusCodeSame(403);
    }

    public function testAPathOutsideTheManifestIsNotFound(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithFile($owner);
        $url = $this->signedUrl($project, 'OEBPS/images/nothere.png');

        $this->client->request('GET', $url);

        self::assertResponseStatusCodeSame(404);
    }

    public function testTraversalIsNotFound(): void
    {
        $owner = $this->createUser();
        $project = $this->projectWithFile($owner);
        $url = $this->signedUrl($project, '../../etc/passwd');

        $this->client->request('GET', $url);

        self::assertResponseStatusCodeSame(404);
    }

    private function signedUrl(Project $project, string $path): string
    {
        $signer = self::getContainer()->get(AssetUrlSigner::class);

        return \sprintf(
            '/api/projects/%s/assets/%s?t=%s',
            $project->getId(),
            $path,
            $signer->sign((string) $project->getId(), $path),
        );
    }

    private function projectWithFile(User $owner): Project
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

        $entityManager->flush();

        return $project;
    }
}
