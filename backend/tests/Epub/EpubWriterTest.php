<?php

declare(strict_types=1);

namespace App\Tests\Epub;

use App\Epub\EpubWriter;
use App\Epub\InvalidEpubException;
use App\Tests\Support\EpubBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class EpubWriterTest extends TestCase
{
    public function testKeepsTheMimetypeFirstAndUncompressed(): void
    {
        $source = EpubBuilder::create()->withChapter('ch1.xhtml', '<p>Hello</p>')->build();
        $target = $this->targetPath();

        $this->writer()->write($source, ['OEBPS/ch1.xhtml' => $this->document('<p>Witaj</p>')], $target);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($target));

        $first = $zip->statIndex(0);

        if (false === $first) {
            self::fail('The written archive has no entries.');
        }

        // OCF: "mimetype" musi byc pierwszym wpisem i nie moze byc spakowany.
        // Czytnik, ktory sprawdza to do konca, odrzuca plik z innym ukladem.
        self::assertSame('mimetype', $first['name']);
        self::assertSame(\ZipArchive::CM_STORE, $first['comp_method']);

        $zip->close();
    }

    public function testReplacesOnlyTheEntriesItIsGiven(): void
    {
        $png = $this->png();
        $source = EpubBuilder::create()
            ->withChapter('ch1.xhtml', '<p>Hello</p>')
            ->withChapter('ch2.xhtml', '<p>Untouched</p>')
            ->withImage('images/cover.png', $png)
            ->build();
        $target = $this->targetPath();

        $this->writer()->write($source, ['OEBPS/ch1.xhtml' => $this->document('<p>Witaj</p>')], $target);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($target));

        self::assertStringContainsString('<p>Witaj</p>', (string) $zip->getFromName('OEBPS/ch1.xhtml'));
        self::assertStringNotContainsString('<p>Hello</p>', (string) $zip->getFromName('OEBPS/ch1.xhtml'));
        self::assertStringContainsString('<p>Untouched</p>', (string) $zip->getFromName('OEBPS/ch2.xhtml'));
        // Obraz przechodzi bajt w bajt: eksport nie ma prawa go ruszyc.
        self::assertSame($png, $zip->getFromName('OEBPS/images/cover.png'));
        self::assertStringContainsString('<dc:title>', (string) $zip->getFromName('OEBPS/content.opf'));
        self::assertNotFalse($zip->getFromName('META-INF/container.xml'));

        $zip->close();
    }

    public function testForcesAnUncompressedMimetypeWhenTheBookHasItDeflated(): void
    {
        $source = $this->targetPath();
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($source, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        // Powtorzony string, zeby deflate faktycznie sie oplacil - inaczej
        // libzip sam zapisze wpis bez kompresji i test niczego nie dowodzi.
        $zip->addFromString('mimetype', str_repeat('application/epub+zip', 20));
        $zip->addFromString('OEBPS/ch1.xhtml', $this->document('<p>Hello</p>'));
        $zip->close();

        $target = $this->targetPath();
        $this->writer()->write($source, [], $target);

        $written = new \ZipArchive();
        self::assertTrue($written->open($target));

        $stat = $written->statName('mimetype');

        if (false === $stat) {
            self::fail('The written archive has no mimetype entry.');
        }

        // Ksiazka z zepsutym mimetype to nie powod, zeby wydac uzytkownikowi
        // druga taka - kolejnosci wpisow nie przestawimy, kompresje owszem.
        self::assertSame(\ZipArchive::CM_STORE, $stat['comp_method']);

        $written->close();
    }

    public function testRefusesToInventAnEntryTheBookDoesNotHave(): void
    {
        $source = EpubBuilder::create()->withChapter('ch1.xhtml', '<p>Hello</p>')->build();
        $target = $this->targetPath();

        $this->expectException(InvalidEpubException::class);

        // addFromString stworzyloby wpis, ktorego w ksiazce nie ma - czyli
        // dolozyloby do niej plik zamiast podmienic rozdzial.
        $this->writer()->write($source, ['OEBPS/nie-ma-takiego.xhtml' => $this->document('<p>X</p>')], $target);
    }

    public function testLeavesTheSourceArchiveAlone(): void
    {
        $source = EpubBuilder::create()->withChapter('ch1.xhtml', '<p>Hello</p>')->build();
        $before = md5_file($source);
        $target = $this->targetPath();

        $this->writer()->write($source, ['OEBPS/ch1.xhtml' => $this->document('<p>Witaj</p>')], $target);

        // Oryginal na wolumenie jest jedynym zrodlem prawdy o ksiazce -
        // eksport buduje obok, nigdy w miejscu.
        self::assertSame($before, md5_file($source));
    }

    private function writer(): EpubWriter
    {
        return new EpubWriter(new Filesystem());
    }

    private function document(string $body): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<html xmlns="http://www.w3.org/1999/xhtml"><head><title>T</title></head>'
            .'<body>'.$body.'</body></html>';
    }

    private function png(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
    }

    private function targetPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'export');

        if (false === $path) {
            self::fail('Could not create a temporary file.');
        }

        return $path;
    }
}
