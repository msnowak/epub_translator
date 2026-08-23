<?php

declare(strict_types=1);

namespace App\Tests\Epub;

use App\Epub\EpubReader;
use App\Epub\InvalidEpubException;
use App\Tests\Support\EpubBuilder;
use PHPUnit\Framework\TestCase;

final class EpubReaderTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    public function testReadsSpineInOrder(): void
    {
        $path = EpubBuilder::create()
            ->withChapter('ch1.xhtml', '<p>One</p>')
            ->withChapter('ch2.xhtml', '<p>Two</p>')
            ->build();

        $package = (new EpubReader())->open($path);

        self::assertSame(['OEBPS/ch1.xhtml', 'OEBPS/ch2.xhtml'], $package->spineHrefs());

        $package->close();
    }

    public function testReadsMetadata(): void
    {
        $path = EpubBuilder::create()
            ->withTitle('Wichrowe Wzgórza')
            ->withLanguage('en')
            ->withChapter('ch1.xhtml', '<p>One</p>')
            ->build();

        $package = (new EpubReader())->open($path);

        self::assertSame('Wichrowe Wzgórza', $package->title());
        self::assertSame('en', $package->language());

        $package->close();
    }

    public function testReadsChapterContent(): void
    {
        $path = EpubBuilder::create()->withChapter('ch1.xhtml', '<p>Hello</p>')->build();

        $package = (new EpubReader())->open($path);

        self::assertStringContainsString('<p>Hello</p>', $package->read('OEBPS/ch1.xhtml'));

        $package->close();
    }

    public function testManifestListsImages(): void
    {
        $path = EpubBuilder::create()
            ->withChapter('ch1.xhtml', '<p>Hello</p>')
            ->withImage('images/cover.png', 'binary-content')
            ->build();

        $package = (new EpubReader())->open($path);

        self::assertContains('OEBPS/images/cover.png', $package->manifestHrefs());

        $package->close();
    }

    public function testDecodesPercentEncodedManifestHrefs(): void
    {
        $path = EpubBuilder::create()
            ->withChapter('ch1.xhtml', '<p>Hello</p>')
            ->withImage('images/my image.png', 'binary-content', 'images/my%20image.png')
            ->build();

        $package = (new EpubReader())->open($path);

        // Href w OPF jest URL-em, nazwa wpisu w zipie nie. Bez dekodowania
        // manifest opisuje plik, ktorego w archiwum nie ma.
        self::assertContains('OEBPS/images/my image.png', $package->manifestHrefs());
        self::assertSame('binary-content', $package->read('OEBPS/images/my image.png'));

        $package->close();
    }

    public function testDecodesPercentEncodedSpineHrefs(): void
    {
        $path = EpubBuilder::create()
            ->withChapter('rozdzial pierwszy.xhtml', '<p>Hello</p>', 'rozdzial%20pierwszy.xhtml')
            ->build();

        $package = (new EpubReader())->open($path);

        // Spine czyta hrefy z manifestu, wiec dekodowanie obejmuje takze
        // rozdzialy - to z nich powstaje Chapter::$href, po ktorym eksport
        // siega do zipa.
        self::assertSame(['OEBPS/rozdzial pierwszy.xhtml'], $package->spineHrefs());
        self::assertStringContainsString('<p>Hello</p>', $package->read('OEBPS/rozdzial pierwszy.xhtml'));

        $package->close();
    }

    public function testExposesThePathToThePackageDocument(): void
    {
        $path = EpubBuilder::create()->withChapter('ch1.xhtml', '<p>Hello</p>')->build();

        $package = (new EpubReader())->open($path);

        // Pakiet nie jest wymieniony we wlasnym manifescie, wiec eksport nie
        // ma jak go znalezc inaczej niz od czytnika.
        self::assertSame('OEBPS/content.opf', $package->opfHref());

        $package->close();
    }

    public function testRejectsArchiveWithoutContainerXml(): void
    {
        $path = EpubBuilder::create()
            ->withoutContainerXml()
            ->withChapter('ch1.xhtml', '<p>Hello</p>')
            ->build();

        $this->expectException(InvalidEpubException::class);

        (new EpubReader())->open($path);
    }

    public function testRejectsArchiveWithNothingToTranslate(): void
    {
        // Builder bez rozdzialow daje OPF z pustym manifestem i pustym spine -
        // plik jest poprawnym zipem z container.xml, ale nie ma czego tlumaczyc.
        $path = EpubBuilder::create()->build();

        $this->expectException(InvalidEpubException::class);

        (new EpubReader())->open($path);
    }

    public function testRejectsUnreadableFile(): void
    {
        $path = EpubBuilder::create()->corrupted()->build();

        $this->expectException(InvalidEpubException::class);

        (new EpubReader())->open($path);
    }

    public function testReadsAPackageWhosePathIsPercentEncodedInTheContainer(): void
    {
        // Prawdziwa spacja w nazwie wpisu, zakodowana w container.xml - tak
        // robi generator zgodny ze specyfikacja.
        $path = $this->archiveWithOpfAt('OEBPS text/content.opf', 'OEBPS%20text/content.opf');

        $package = (new EpubReader())->open($path);

        self::assertSame(['OEBPS text/ch1.xhtml'], $package->spineHrefs());

        $package->close();
    }

    public function testReadsAPackageWhoseEntryNameContainsALiteralPercentTwenty(): void
    {
        // Naiwny generator: doslowne "%20" w nazwie wpisu i ten sam doslowny
        // ciag w container.xml. Dekodowanie bez odwrotu zepsuloby taka ksiazke.
        $path = $this->archiveWithOpfAt('OEBPS%20text/content.opf', 'OEBPS%20text/content.opf');

        $package = (new EpubReader())->open($path);

        self::assertSame(['OEBPS%20text/ch1.xhtml'], $package->spineHrefs());

        $package->close();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->temporaryFiles = [];

        parent::tearDown();
    }

    /**
     * Builds an archive whose package document sits at $entryName inside the
     * zip while container.xml points at it as $declaredPath. EpubBuilder always
     * puts the package in OEBPS/content.opf, and bending it for one edge case
     * would touch a dozen other tests.
     */
    private function archiveWithOpfAt(string $entryName, string $declaredPath): string
    {
        $path = tempnam(sys_get_temp_dir(), 'reader');

        if (false === $path) {
            self::fail('Could not create a temporary file.');
        }

        $this->temporaryFiles[] = $path;

        $chapterEntry = \dirname($entryName).'/ch1.xhtml';

        $zip = new \ZipArchive();

        if (true !== $zip->open($path, \ZipArchive::OVERWRITE)) {
            self::fail('Could not create the archive.');
        }

        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->addFromString('META-INF/container.xml', \sprintf(
            <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
                    <rootfiles>
                        <rootfile full-path="%s" media-type="application/oebps-package+xml"/>
                    </rootfiles>
                </container>
                XML,
            $declaredPath,
        ));
        $zip->addFromString($entryName, <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="id">
                <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
                    <dc:title>Test</dc:title>
                </metadata>
                <manifest>
                    <item id="c1" href="ch1.xhtml" media-type="application/xhtml+xml"/>
                </manifest>
                <spine>
                    <itemref idref="c1"/>
                </spine>
            </package>
            XML);
        $zip->addFromString($chapterEntry, '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Tekst</p></body></html>');

        self::assertTrue($zip->close());

        return $path;
    }
}
