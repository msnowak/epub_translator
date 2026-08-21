<?php

declare(strict_types=1);

namespace App\Tests\Epub;

use App\Epub\InvalidEpubException;
use App\Epub\XhtmlDocument;
use PHPUnit\Framework\TestCase;

final class XhtmlDocumentTest extends TestCase
{
    public function testReadsInnerHtmlWithMarkup(): void
    {
        $document = (new XhtmlDocument())->load($this->chapter('<p>To jest <em>ważne</em>.</p>'));
        $paragraph = $document->getElementsByTagName('p')->item(0);

        self::assertInstanceOf(\DOMElement::class, $paragraph);
        self::assertSame('To jest <em>ważne</em>.', (new XhtmlDocument())->innerHtml($paragraph));
    }

    public function testReplacesInnerHtmlKeepingTheNamespace(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p>Original.</p>'));
        $paragraph = $document->getElementsByTagName('p')->item(0);

        self::assertInstanceOf(\DOMElement::class, $paragraph);

        $xhtml->replaceInnerHtml($paragraph, 'To jest <em>ważne</em>.');
        $saved = $xhtml->save($document);

        self::assertStringContainsString('<em>ważne</em>', $saved);
        // Gdyby fragment wyladowal poza przestrzenia nazw, serializacja
        // dopisalaby pusty xmlns i przegladarka przestalaby traktowac
        // znacznik jak XHTML.
        self::assertStringNotContainsString('xmlns=""', $saved);
        self::assertStringNotContainsString('Original.', $saved);
    }

    public function testReplacesInnerHtmlInADocumentWithoutNamespace(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load('<html><body><p>Original.</p></body></html>');
        $paragraph = $document->getElementsByTagName('p')->item(0);

        self::assertInstanceOf(\DOMElement::class, $paragraph);

        $xhtml->replaceInnerHtml($paragraph, 'Nowa <strong>treść</strong>.');
        $saved = $xhtml->save($document);

        self::assertStringContainsString('<strong>treść</strong>', $saved);
        self::assertStringNotContainsString('xmlns', $saved);
    }

    public function testReplacesInnerHtmlWithPlainText(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p>Original.</p>'));
        $paragraph = $document->getElementsByTagName('p')->item(0);

        self::assertInstanceOf(\DOMElement::class, $paragraph);

        $xhtml->replaceInnerHtml($paragraph, 'Zwykły tekst bez znaczników.');

        self::assertStringContainsString('Zwykły tekst bez znaczników.', $xhtml->save($document));
    }

    public function testEmptyReplacementClearsTheElement(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p>Original.</p>'));
        $paragraph = $document->getElementsByTagName('p')->item(0);

        self::assertInstanceOf(\DOMElement::class, $paragraph);

        $xhtml->replaceInnerHtml($paragraph, '');

        self::assertSame('', $xhtml->innerHtml($paragraph));
    }

    public function testMalformedFragmentFallsBackToText(): void
    {
        // Model albo czlowiek potrafi zostawic niedomkniety znacznik. Lepiej
        // wstawic sam tekst niz wywrocic caly rozdzial.
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p>Original.</p>'));
        $paragraph = $document->getElementsByTagName('p')->item(0);

        self::assertInstanceOf(\DOMElement::class, $paragraph);

        $xhtml->replaceInnerHtml($paragraph, 'Zepsute <em>coś');

        $saved = $xhtml->save($document);
        self::assertStringContainsString('Zepsute', $saved);
        self::assertStringContainsString('coś', $saved);
    }

    public function testRejectsUnparsableDocument(): void
    {
        $this->expectException(InvalidEpubException::class);

        (new XhtmlDocument())->load('<html><body><p>niedomkniete');
    }

    public function testSaveRoundTripsAnUntouchedDocument(): void
    {
        $xhtml = new XhtmlDocument();
        $source = $this->chapter('<p>Bez zmian.</p>');

        self::assertStringContainsString('<p>Bez zmian.</p>', $xhtml->save($xhtml->load($source)));
    }

    private function chapter(string $body): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<html xmlns="http://www.w3.org/1999/xhtml"><head><title>T</title></head>'
            .'<body>'.$body.'</body></html>';
    }
}
