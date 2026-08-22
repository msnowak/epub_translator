<?php

declare(strict_types=1);

namespace App\Tests\Epub;

use App\Epub\EpubReader;
use App\Epub\InvalidEpubException;
use App\Tests\Support\EpubBuilder;
use PHPUnit\Framework\TestCase;

final class EpubReaderTest extends TestCase
{
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
}
