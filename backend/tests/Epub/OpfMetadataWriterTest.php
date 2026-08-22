<?php

declare(strict_types=1);

namespace App\Tests\Epub;

use App\Epub\InvalidEpubException;
use App\Epub\OpfMetadataWriter;
use App\Epub\XhtmlDocument;
use PHPUnit\Framework\TestCase;

final class OpfMetadataWriterTest extends TestCase
{
    public function testSetsTheLanguageAndTheTitle(): void
    {
        $updated = $this->writer()->update(
            $this->package('<dc:title>Wuthering Heights</dc:title><dc:language>en</dc:language>'),
            'pl',
            'Wichrowe Wzgórza',
        );

        self::assertStringContainsString('<dc:language>pl</dc:language>', $updated);
        self::assertStringContainsString('<dc:title>Wichrowe Wzgórza</dc:title>', $updated);
        self::assertStringNotContainsString('Wuthering Heights', $updated);
    }

    public function testKeepsTheAttributesOfTheElementsItRewrites(): void
    {
        $updated = $this->writer()->update(
            $this->package('<dc:title id="t1">Old</dc:title><dc:language id="l1">en</dc:language>'),
            'pl',
            'Nowy',
        );

        // Identyfikatory sa celem atrybutow "refines" w EPUB 3 - podmiana
        // tresci nie moze ich zgubic, bo osierocilaby polowe metadanych.
        self::assertStringContainsString('<dc:title id="t1">Nowy</dc:title>', $updated);
        self::assertStringContainsString('<dc:language id="l1">pl</dc:language>', $updated);
    }

    public function testDropsTheRemainingLanguages(): void
    {
        $updated = $this->writer()->update(
            $this->package('<dc:title>T</dc:title><dc:language>en</dc:language><dc:language>fr</dc:language>'),
            'pl',
            'T',
        );

        // Po tlumaczeniu ksiazka jest w jednym jezyku; drugi wpis mowilby
        // czytnikowi, ze nadal jest francuska.
        self::assertSame(1, substr_count($updated, '<dc:language'));
        self::assertStringContainsString('<dc:language>pl</dc:language>', $updated);
        self::assertStringNotContainsString('fr', $updated);
    }

    public function testAddsTheLanguageWhenTheBookDeclaresNone(): void
    {
        $updated = $this->writer()->update($this->package('<dc:title>T</dc:title>'), 'pl', 'T');

        self::assertStringContainsString('<dc:language>pl</dc:language>', $updated);
    }

    public function testLeavesEveryOtherPieceOfMetadataAlone(): void
    {
        $updated = $this->writer()->update(
            $this->package(
                '<dc:identifier id="bookid">urn:uuid:test</dc:identifier>'
                .'<dc:creator>Emily Brontë</dc:creator>'
                .'<dc:title>T</dc:title><dc:language>en</dc:language>',
            ),
            'pl',
            'T',
        );

        self::assertStringContainsString('urn:uuid:test', $updated);
        self::assertStringContainsString('Emily Brontë', $updated);
        self::assertStringContainsString('<manifest/>', $updated);
    }

    public function testEscapesWhatItWrites(): void
    {
        $updated = $this->writer()->update(
            $this->package('<dc:title>T</dc:title><dc:language>en</dc:language>'),
            'pl',
            'Tom & Jerry <b>',
        );

        // Tytul przychodzi od uzytkownika i idzie do XML-a - niezescape'owany
        // rozwalilby pakiet, czyli cala ksiazke.
        self::assertStringContainsString('Tom &amp; Jerry &lt;b&gt;', $updated);
    }

    public function testRejectsAPackageWithoutMetadata(): void
    {
        $this->expectException(InvalidEpubException::class);

        $this->writer()->update(
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<package xmlns="http://www.idpf.org/2007/opf" version="3.0"><manifest/></package>',
            'pl',
            'T',
        );
    }

    public function testRejectsSomethingThatIsNotXml(): void
    {
        $this->expectException(InvalidEpubException::class);

        $this->writer()->update('to nie jest XML', 'pl', 'T');
    }

    private function writer(): OpfMetadataWriter
    {
        return new OpfMetadataWriter(new XhtmlDocument());
    }

    private function package(string $metadata): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">'
            .'<metadata xmlns:dc="http://purl.org/dc/elements/1.1/">'.$metadata.'</metadata>'
            .'<manifest/><spine/></package>';
    }
}
