<?php

declare(strict_types=1);

namespace App\Tests\Preview;

use App\Epub\BlockExtractor;
use App\Epub\XhtmlDocument;
use App\Preview\AssetUrlSigner;
use App\Preview\PreviewDecorator;
use PHPUnit\Framework\TestCase;

final class PreviewDecoratorTest extends TestCase
{
    private const string PROJECT = '01920000-0000-7000-8000-000000000000';

    public function testMarksBlocksWithTheirSegmentId(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p>One.</p><p>Two.</p>'));

        $this->decorate($xhtml, $document, [
            0 => 'seg-one',
            1 => 'seg-two',
        ]);

        $saved = $xhtml->save($document);

        self::assertStringContainsString('data-segment-id="seg-one"', $saved);
        self::assertStringContainsString('data-segment-id="seg-two"', $saved);
    }

    public function testMarksTheBlocksItIsGivenAndNotWhatItFindsItself(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p><em></em></p><p>Two.</p>'));

        // Pierwszy akapit po zlozeniu tlumaczenia nie ma juz tekstu, wiec
        // wlasne wyliczenie blokow by go pominelo i "seg-two" trafiloby
        // w drugi akapit z indeksem 0.
        $blocks = array_values(iterator_to_array($document->getElementsByTagName('p')));

        $this->decorator()->decorate($document, $blocks, self::PROJECT, 'OEBPS/ch1.xhtml', [
            1 => 'seg-two',
        ]);

        self::assertStringContainsString('<p data-segment-id="seg-two">Two.</p>', $xhtml->save($document));
    }

    public function testRewritesRelativeAssetPathsAgainstTheChapter(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p><img src="images/cover.png"/></p>'));

        $this->decorate($xhtml, $document, []);

        $saved = $xhtml->save($document);

        // Rozdzial lezy w OEBPS/, wiec images/ to OEBPS/images/.
        self::assertStringContainsString('/api/projects/'.self::PROJECT.'/assets/OEBPS/images/cover.png?t=', $saved);
    }

    public function testRewritesStylesheetLinks(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load(
            '<?xml version="1.0" encoding="utf-8"?>'
            .'<html xmlns="http://www.w3.org/1999/xhtml">'
            .'<head><link rel="stylesheet" href="../styles/main.css"/></head>'
            .'<body><p>Text.</p></body></html>',
        );

        $this->decorate($xhtml, $document, [], 'OEBPS/text/ch1.xhtml');

        self::assertStringContainsString('/assets/OEBPS/styles/main.css?t=', $xhtml->save($document));
    }

    public function testRewritesNamespacedImageHrefs(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter(
            '<p><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
            .'<image xlink:href="images/cover.jpg"/></svg></p>',
        ));

        $this->decorate($xhtml, $document, []);

        // Okladka z EPUB 2 nosi adres w przestrzeni xlink - bez dopasowania
        // po nazwie lokalnej zostawalaby nieprzepisana i nie ladowala sie.
        self::assertStringContainsString(
            'xlink:href="/api/projects/'.self::PROJECT.'/assets/OEBPS/images/cover.jpg?t=',
            $xhtml->save($document),
        );
    }

    public function testSignsThePathTheControllerWillSeeAndEncodesTheUrl(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p><img src="images/my%20image.png"/></p>'));

        $this->decorate($xhtml, $document, []);

        $saved = $xhtml->save($document);

        self::assertStringContainsString('/assets/OEBPS/images/my%20image.png?t=', $saved);
        self::assertSame(1, preg_match('/\?t=([^"&#]+)/', $saved, $matches));

        // Router dekoduje sciezke przed dopasowaniem trasy, wiec podpis musi
        // dotyczyc nazwy ze spacja, a nie zapisu procentowego.
        self::assertTrue(
            (new AssetUrlSigner('sekret'))->isValid(self::PROJECT, 'OEBPS/images/my image.png', $matches[1] ?? ''),
        );
    }

    public function testDoesNotSignAbsoluteUrlsOrAnchors(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter(
            '<p><img src="https://example.com/a.png"/>'
            .'<a href="https://example.com/a">link</a><a href="#footnote">przypis</a></p>',
        ));

        $this->decorate($xhtml, $document, []);

        $saved = $xhtml->save($document);

        self::assertStringContainsString('src="https://example.com/a.png"', $saved);
        self::assertStringContainsString('https://example.com/a', $saved);
        self::assertStringContainsString('"#footnote"', $saved);
        self::assertStringNotContainsString('/assets/', $saved);
    }

    public function testMovesLinkTargetsOutOfTheDocument(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p><a href="ch2.xhtml">dalej</a></p>'));

        $this->decorate($xhtml, $document, []);

        $anchor = $document->getElementsByTagName('a')->item(0);

        self::assertInstanceOf(\DOMElement::class, $anchor);

        // Odsylacz do rozdzialu nie jest zasobem: podpisany adres wpuscilby
        // przegladarke do surowego pliku z ksiazki.
        self::assertFalse($anchor->hasAttribute('href'));
        self::assertSame('ch2.xhtml', $anchor->getAttribute('data-epub-href'));
        self::assertStringNotContainsString('/assets/', $xhtml->save($document));
    }

    public function testStripsScriptElements(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p>Text.</p><script>alert(1)</script>'));

        $this->decorate($xhtml, $document, []);

        $saved = $xhtml->save($document);

        self::assertStringNotContainsString('alert(1)', $saved);
        self::assertStringNotContainsString('<script', $saved);
    }

    public function testStripsEmbeddedBrowsingContexts(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter(
            '<p>Text.</p><iframe src="evil.xhtml"></iframe>'
            .'<object data="evil.swf"></object><embed src="evil.svg"/>',
        ));

        $this->decorate($xhtml, $document, []);

        $saved = $xhtml->save($document);

        self::assertStringNotContainsString('<iframe', $saved);
        self::assertStringNotContainsString('<object', $saved);
        self::assertStringNotContainsString('<embed', $saved);
        self::assertStringNotContainsString('/assets/', $saved);
    }

    public function testStripsMetaRefresh(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load(
            '<?xml version="1.0" encoding="utf-8"?>'
            .'<html xmlns="http://www.w3.org/1999/xhtml">'
            .'<head><meta http-equiv="refresh" content="0;url=evil.xhtml"/></head>'
            .'<body><p>Text.</p></body></html>',
        );

        $this->decorate($xhtml, $document, []);

        self::assertStringNotContainsString('refresh', $xhtml->save($document));
    }

    public function testStripsInlineEventHandlers(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p onclick="steal()">Text.</p>'));

        $this->decorate($xhtml, $document, []);

        self::assertStringNotContainsString('onclick', $xhtml->save($document));
    }

    public function testStripsJavascriptUrls(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p><a href="javascript:steal()">klik</a></p>'));

        $this->decorate($xhtml, $document, []);

        self::assertStringNotContainsString('javascript:', $xhtml->save($document));
    }

    public function testStripsNamespacedJavascriptUrls(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter(
            '<p><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
            .'<a xlink:href="javascript:steal()">klik</a></svg></p>',
        ));

        $this->decorate($xhtml, $document, []);

        // removeAttribute() szuka nazwy kwalifikowanej, wiec "xlink:href"
        // przezywalo usuwanie po nazwie lokalnej.
        self::assertStringNotContainsString('javascript:', $xhtml->save($document));
    }

    /**
     * @param array<int, string> $segmentIdsByNodeIndex
     */
    private function decorate(
        XhtmlDocument $xhtml,
        \DOMDocument $document,
        array $segmentIdsByNodeIndex,
        string $chapterHref = 'OEBPS/ch1.xhtml',
    ): void {
        $blocks = (new BlockExtractor($xhtml))->elements($document);

        $this->decorator()->decorate($document, $blocks, self::PROJECT, $chapterHref, $segmentIdsByNodeIndex);
    }

    private function decorator(): PreviewDecorator
    {
        return new PreviewDecorator(new AssetUrlSigner('sekret'));
    }

    private function chapter(string $body): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<html xmlns="http://www.w3.org/1999/xhtml"><head><title>T</title></head>'
            .'<body>'.$body.'</body></html>';
    }
}
